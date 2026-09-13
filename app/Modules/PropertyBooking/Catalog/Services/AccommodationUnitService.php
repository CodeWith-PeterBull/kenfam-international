<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/** Owns concrete accommodation inventory, activation, and readiness changes. */
final readonly class AccommodationUnitService
{
    /** Create the concrete-unit service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Property $property, UnitType $unitType, array $attributes, User $actor): AccommodationUnit
    {
        $this->assertSameProperty($property, $unitType);

        return $this->database->transaction(function () use ($property, $unitType, $attributes, $actor): AccommodationUnit {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $unitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->getKey());
            $this->assertSameProperty($property, $unitType);

            $unit = new AccommodationUnit;
            $unit->forceFill([
                'property_id' => $property->getKey(),
                'unit_type_id' => $unitType->getKey(),
                'operational_status' => UnitOperationalStatus::Ready,
                'is_active' => true,
                'last_ready_at' => now(),
            ]);
            $unit->fill($this->payload($attributes));
            $unit->forceFill([
                'property_id' => $property->getKey(),
                'unit_type_id' => $unitType->getKey(),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValid($unit);
            $unit->save();

            $this->activities->record(
                activityType: 'property-booking.unit.created',
                description: "Accommodation unit created: {$unit->code}",
                actor: $actor,
                subject: $unit,
                properties: ['property_ulid' => $property->ulid, 'unit_type_ulid' => $unitType->ulid],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $unit->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(AccommodationUnit $unit, UnitType $unitType, array $attributes, User $actor): AccommodationUnit
    {
        return $this->database->transaction(function () use ($unit, $unitType, $attributes, $actor): AccommodationUnit {
            $unit = AccommodationUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $unitType = UnitType::query()->lockForUpdate()->findOrFail($unitType->getKey());
            if ($unit->property_id !== $unitType->property_id) {
                throw new CatalogException('The selected unit type belongs to a different property.');
            }
            if ($unit->unit_type_id !== $unitType->getKey() && $this->hasActiveAssignments($unit)) {
                throw new CatalogException('A concrete unit with an active booking assignment cannot change unit type.');
            }

            $unit->fill($this->payload($attributes));
            $unit->forceFill(['unit_type_id' => $unitType->getKey(), 'updated_by' => $actor->getKey()]);
            $this->assertValid($unit);
            $changes = array_values(array_diff(array_keys($unit->getDirty()), ['updated_by']));
            $unit->save();

            $this->activities->record(
                activityType: 'property-booking.unit.updated',
                description: "Accommodation unit updated: {$unit->code}",
                actor: $actor,
                subject: $unit,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-catalog',
            );

            return $unit->refresh();
        });
    }

    /** Activate or deactivate future allocation after checking active ownership. */
    public function setActive(AccommodationUnit $unit, bool $active, User $actor): AccommodationUnit
    {
        return $this->database->transaction(function () use ($unit, $active, $actor): AccommodationUnit {
            $unit = AccommodationUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            if ($unit->is_active === $active) {
                return $unit;
            }
            if (! $active && $this->hasActiveAssignments($unit)) {
                throw new CatalogException('A concrete unit with an active booking assignment cannot be deactivated.');
            }

            $unit->forceFill(['is_active' => $active, 'updated_by' => $actor->getKey()])->save();
            $this->activities->record(
                activityType: 'property-booking.unit.activation-changed',
                description: 'Accommodation unit '.($active ? 'activated' : 'deactivated').": {$unit->code}",
                actor: $actor,
                subject: $unit,
                properties: ['is_active' => $active],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $unit->refresh();
        });
    }

    /** Move a unit through the documented operational-readiness state machine. */
    public function transitionReadiness(AccommodationUnit $unit, UnitOperationalStatus $status, User $actor): AccommodationUnit
    {
        return $this->database->transaction(function () use ($unit, $status, $actor): AccommodationUnit {
            $unit = AccommodationUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $previous = $unit->operational_status;
            if ($previous === $status) {
                return $unit;
            }
            if ($this->hasCheckedInAssignment($unit)) {
                throw new CatalogException('Readiness cannot change while a checked-in booking occupies this unit.');
            }

            if (! $previous->canTransitionTo($status)) {
                throw new CatalogException("A unit cannot move directly from {$previous->label()} to {$status->label()}.");
            }

            $unit->forceFill([
                'operational_status' => $status,
                'last_ready_at' => $status === UnitOperationalStatus::Ready ? now() : $unit->last_ready_at,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->activities->record(
                activityType: 'property-booking.unit.readiness-changed',
                description: "Accommodation unit readiness changed to {$status->label()}: {$unit->code}",
                actor: $actor,
                subject: $unit,
                properties: ['from' => $previous->value, 'to' => $status->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-readiness',
            );

            return $unit->refresh();
        });
    }

    /** Soft-archive an unused concrete unit while retaining operational history. */
    public function archive(AccommodationUnit $unit, User $actor): void
    {
        $this->database->transaction(function () use ($unit, $actor): void {
            $unit = AccommodationUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            if ($this->hasActiveAssignments($unit)) {
                throw new CatalogException('A concrete unit with an active booking assignment cannot be archived.');
            }
            if ($unit->availabilityBlocks()->where('status', AvailabilityBlockStatus::Active->value)->exists()) {
                throw new CatalogException('Release active availability blocks before archiving this concrete unit.');
            }

            $this->activities->record(
                activityType: 'property-booking.unit.archived',
                description: "Accommodation unit archived: {$unit->code}",
                actor: $actor,
                subject: $unit,
                properties: ['property_id' => $unit->property_id, 'unit_type_id' => $unit->unit_type_id],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );
            $unit->delete();
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, ['code', 'display_name', 'floor_label', 'location_note', 'internal_note']);
        if (array_key_exists('code', $payload)) {
            $payload['code'] = strtoupper(trim((string) $payload['code']));
        }
        foreach (['display_name', 'floor_label', 'location_note', 'internal_note'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string) $payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** Assert minimum concrete-unit identity. */
    private function assertValid(AccommodationUnit $unit): void
    {
        if (blank($unit->code)) {
            throw new CatalogException('Accommodation units require a stable operational code.');
        }
    }

    /** Assert a unit type belongs to the owning property. */
    private function assertSameProperty(Property $property, UnitType $unitType): void
    {
        if ($unitType->property_id !== $property->getKey()) {
            throw new CatalogException('The selected unit type belongs to a different property.');
        }
    }

    /** Determine whether the unit currently consumes availability. */
    private function hasActiveAssignments(AccommodationUnit $unit): bool
    {
        return $unit->assignments()->where('status', UnitAssignmentStatus::Active->value)->exists();
    }

    /** Determine whether an active assignment currently represents an in-house stay. */
    private function hasCheckedInAssignment(AccommodationUnit $unit): bool
    {
        return $unit->assignments()
            ->where('status', UnitAssignmentStatus::Active->value)
            ->whereHas('booking', static fn ($booking) => $booking->where('stay_status', StayStatus::CheckedIn->value))
            ->exists();
    }
}
