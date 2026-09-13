<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/** Owns the amenity catalogue and validated property/unit-type associations. */
final readonly class AmenityService
{
    /** Create the amenity service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): Amenity
    {
        return $this->database->transaction(function () use ($attributes, $actor): Amenity {
            $amenity = new Amenity;
            $amenity->fill($this->payload($attributes));
            $this->assertValid($amenity);
            $amenity->save();

            $this->activities->record(
                activityType: 'property-booking.amenity.created',
                description: "Amenity created: {$amenity->name}",
                actor: $actor,
                subject: $amenity,
                properties: ['scope' => $amenity->scope->value],
                source: 'property-booking-catalog',
            );

            return $amenity->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Amenity $amenity, array $attributes, User $actor): Amenity
    {
        return $this->database->transaction(function () use ($amenity, $attributes, $actor): Amenity {
            $amenity = Amenity::query()->lockForUpdate()->findOrFail($amenity->getKey());
            $amenity->fill($this->payload($attributes));
            $this->assertValid($amenity);
            $this->assertScopeChangeAllowed($amenity);
            $changes = array_keys($amenity->getDirty());
            $amenity->save();

            $this->activities->record(
                activityType: 'property-booking.amenity.updated',
                description: "Amenity updated: {$amenity->name}",
                actor: $actor,
                subject: $amenity,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-catalog',
            );

            return $amenity->refresh();
        });
    }

    /** Change amenity availability for new assignments. */
    public function setActive(Amenity $amenity, bool $active, User $actor): Amenity
    {
        $amenity = Amenity::query()->findOrFail($amenity->getKey());
        if ($amenity->is_active === $active) {
            return $amenity;
        }

        $amenity->forceFill(['is_active' => $active])->save();
        $this->activities->record(
            activityType: 'property-booking.amenity.visibility-changed',
            description: 'Amenity '.($active ? 'activated' : 'hidden').": {$amenity->name}",
            actor: $actor,
            subject: $amenity,
            properties: ['is_active' => $active],
            source: 'property-booking-catalog',
        );

        return $amenity->refresh();
    }

    /** @param list<int> $amenityIds */
    public function syncPropertyAmenities(Property $property, array $amenityIds, User $actor): Property
    {
        $ids = $this->validatedIds($amenityIds, [AmenityScope::Property, AmenityScope::Both]);

        return $this->database->transaction(function () use ($property, $ids, $actor): Property {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $property->amenities()->sync($ids);
            $this->activities->record(
                activityType: 'property-booking.property.amenities-synced',
                description: "Property amenities updated: {$property->name}",
                actor: $actor,
                subject: $property,
                properties: ['amenity_count' => count($ids)],
                source: 'property-booking-catalog',
            );

            return $property->refresh()->load('amenities');
        });
    }

    /** @param list<int> $amenityIds */
    public function syncUnitTypeAmenities(UnitType $unitType, array $amenityIds, User $actor): UnitType
    {
        $ids = $this->validatedIds($amenityIds, [AmenityScope::Unit, AmenityScope::Both]);

        return $this->database->transaction(function () use ($unitType, $ids, $actor): UnitType {
            $unitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->getKey());
            $unitType->amenities()->sync($ids);
            $this->activities->record(
                activityType: 'property-booking.unit-type.amenities-synced',
                description: "Unit-type amenities updated: {$unitType->name}",
                actor: $actor,
                subject: $unitType,
                properties: ['amenity_count' => count($ids)],
                source: 'property-booking-catalog',
            );

            return $unitType->refresh()->load('amenities');
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, ['name', 'slug', 'scope', 'description', 'icon_key', 'sort_order', 'is_active']);
        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (! array_key_exists('slug', $payload) && filled($payload['name'] ?? null)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }
        foreach (['description', 'icon_key'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string) $payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** Assert identity, scope, and safe icon-library storage. */
    private function assertValid(Amenity $amenity): void
    {
        if (blank($amenity->name) || blank($amenity->slug) || ! $amenity->scope instanceof AmenityScope) {
            throw new CatalogException('Amenities require a name, slug, and valid scope.');
        }
        if ($amenity->icon_key !== null && preg_match('/^[a-z0-9-]+$/', $amenity->icon_key) !== 1) {
            throw new CatalogException('Amenity icons must use a safe icon-library key.');
        }
    }

    /** Reject narrowing a scope while incompatible assignments remain. */
    private function assertScopeChangeAllowed(Amenity $amenity): void
    {
        if (! $amenity->isDirty('scope')) {
            return;
        }
        if ($amenity->scope === AmenityScope::Property && $amenity->unitTypes()->exists()) {
            throw new CatalogException('Remove this amenity from all unit types before limiting it to properties.');
        }
        if ($amenity->scope === AmenityScope::Unit && $amenity->properties()->exists()) {
            throw new CatalogException('Remove this amenity from all properties before limiting it to unit types.');
        }
    }

    /** @param list<int> $amenityIds @param list<AmenityScope> $allowedScopes @return list<int> */
    private function validatedIds(array $amenityIds, array $allowedScopes): array
    {
        $ids = collect($amenityIds)->map(static fn (mixed $id): int => (int) $id)->filter()->unique()->values();
        $valid = Amenity::query()
            ->active()
            ->whereIn('scope', array_map(static fn (AmenityScope $scope): string => $scope->value, $allowedScopes))
            ->whereKey($ids)
            ->pluck('id');

        if ($valid->count() !== $ids->count()) {
            throw new CatalogException('One or more selected amenities are inactive or incompatible with this assignment level.');
        }

        return $valid->map(static fn (mixed $id): int => (int) $id)->all();
    }
}
