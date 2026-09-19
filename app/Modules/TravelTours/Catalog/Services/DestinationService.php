<?php

/**
 * Owns TravelTours destination hierarchy and editorial metadata writes.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\DestinationData;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\HierarchyCycle;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Models\Destination;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Enforce geographic hierarchy, metadata validity, and editorial-state ownership. */
final readonly class DestinationService
{
    /** @var array<string, int> */
    private const TYPE_RANK = [
        DestinationType::Continent->value => 0,
        DestinationType::Country->value => 1,
        DestinationType::Region->value => 2,
        DestinationType::City->value => 3,
        DestinationType::Attraction->value => 4,
    ];

    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Create one destination draft or review record. */
    public function create(DestinationData $data, User $actor): Destination
    {
        return $this->database->transaction(function () use ($data, $actor): Destination {
            if (! in_array($data->status, [PublicationStatus::Draft, PublicationStatus::Review], true)) {
                throw new PublicationBlocked(['New destinations must enter the editorial lifecycle as a draft or review item.']);
            }

            $destination = new Destination;
            $destination->forceFill($this->payload($data));
            $destination->forceFill([
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'published_at' => null,
            ]);
            $this->assertValid($destination);
            $this->assertUnique($destination);
            $this->assertParent($destination, $data->parentId);
            $destination->save();

            return $destination->refresh()->load(['parent', 'media']);
        });
    }

    /** Update editorial metadata without crossing the publication boundary. */
    public function update(Destination $destination, DestinationData $data, User $actor): Destination
    {
        return $this->database->transaction(function () use ($destination, $data, $actor): Destination {
            $destination = Destination::query()->lockForUpdate()->findOrFail($destination->getKey());
            $previousStatus = $destination->status;
            $wasActive = $destination->is_active;
            $this->assertEditorialTransition($previousStatus, $data->status);
            $destination->forceFill($this->payload($data));
            $destination->forceFill([
                'updated_by' => $actor->getKey(),
                'published_at' => $previousStatus === PublicationStatus::Published ? $destination->published_at : null,
            ]);
            $this->assertValid($destination);
            $this->assertUnique($destination);
            $this->assertParent($destination, $data->parentId);
            if ($wasActive && ! $destination->is_active) {
                $this->assertCanDeactivate($destination);
            }
            $destination->save();

            return $destination->refresh()->load(['parent', 'media']);
        });
    }

    /** Toggle assignment availability without deleting destination history. */
    public function setActive(Destination $destination, bool $active, User $actor): Destination
    {
        return $this->database->transaction(function () use ($destination, $active, $actor): Destination {
            $destination = Destination::query()->lockForUpdate()->findOrFail($destination->getKey());
            if ($destination->is_active === $active) {
                return $destination;
            }

            if ($active) {
                $this->assertParent($destination, $destination->parent_id);
            } else {
                $this->assertCanDeactivate($destination);
            }

            $destination->forceFill(['is_active' => $active, 'updated_by' => $actor->getKey()])->save();

            return $destination->refresh()->load(['parent', 'media']);
        });
    }

    /**
     * Make a destination publicly eligible.
     *
     * A public destination must be active and, when nested, sit under a
     * published parent so every storefront breadcrumb resolves. Archived
     * records stay out of this path.
     */
    public function publish(Destination $destination, User $actor): Destination
    {
        Gate::forUser($actor)->authorize('publish', $destination);

        return $this->database->transaction(function () use ($destination, $actor): Destination {
            $destination = Destination::query()->lockForUpdate()->findOrFail($destination->getKey());
            if ($destination->status === PublicationStatus::Published) {
                return $destination;
            }
            if ($destination->status === PublicationStatus::Archived) {
                throw new PublicationBlocked(['Restore an archived destination to a draft before publishing.']);
            }
            if (! $destination->is_active) {
                throw new PublicationBlocked(['Activate this destination before publishing it.']);
            }
            $parent = $destination->parent_id === null ? null : Destination::query()->lockForUpdate()->find($destination->parent_id);
            if ($parent instanceof Destination && $parent->status !== PublicationStatus::Published) {
                throw new PublicationBlocked(['Publish the parent destination first; a public destination requires a published parent.']);
            }

            $destination->forceFill([
                'status' => PublicationStatus::Published,
                'published_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            return $destination->refresh()->load(['parent', 'media']);
        });
    }

    /**
     * Withdraw a destination from public eligibility while keeping its slug and media.
     *
     * Published tours must keep every route stop public, and published child
     * destinations need a published parent, so both block the change.
     */
    public function unpublish(Destination $destination, User $actor): Destination
    {
        Gate::forUser($actor)->authorize('unpublish', $destination);

        return $this->database->transaction(function () use ($destination, $actor): Destination {
            $destination = Destination::query()->lockForUpdate()->findOrFail($destination->getKey());
            if ($destination->status !== PublicationStatus::Published) {
                throw new PublicationBlocked(['Only a published destination can be unpublished.']);
            }
            $publishedTours = $destination->tours()->where('travel_tours.status', PublicationStatus::Published->value)->count();
            if ($publishedTours > 0) {
                throw new PublicationBlocked([trans_choice(':count published tour visits this destination; unpublish it or change its route first.|:count published tours visit this destination; unpublish them or change their routes first.', $publishedTours, ['count' => $publishedTours])]);
            }
            if ($destination->children()->where('status', PublicationStatus::Published->value)->exists()) {
                throw new PublicationBlocked(['Unpublish its published child destinations first.']);
            }

            $destination->forceFill([
                'status' => PublicationStatus::Draft,
                'published_at' => null,
                'updated_by' => $actor->getKey(),
            ])->save();

            return $destination->refresh()->load(['parent', 'media']);
        });
    }

    /** Convert validated input into normalized persistence values. */
    private function payload(DestinationData $data): array
    {
        $name = trim($data->name);

        return [
            'parent_id' => $data->parentId,
            'type' => $data->type,
            'country_code' => $this->countryCode($data->countryCode),
            'code' => $this->uppercaseText($data->code, 40, 'Destination code'),
            'name' => $name,
            'slug' => Str::slug($data->slug ?: $name),
            'short_description' => $this->optionalText($data->shortDescription, 320, 'Short description'),
            'description' => $this->optionalText($data->description, 50_000, 'Destination description'),
            'latitude' => $this->coordinate($data->latitude),
            'longitude' => $this->coordinate($data->longitude),
            'timezone' => $this->optionalText($data->timezone, 64, 'Timezone'),
            'is_featured' => $data->isFeatured,
            'is_active' => $data->isActive,
            'status' => $data->status,
            'sort_order' => max(0, $data->sortOrder),
            'meta_title' => $this->optionalText($data->metaTitle, 160, 'Meta title'),
            'meta_description' => $this->optionalText($data->metaDescription, 320, 'Meta description'),
        ];
    }

    /** Validate identity, geography, timezone, and coordinate pairing. */
    private function assertValid(Destination $destination): void
    {
        if (blank($destination->name) || blank($destination->slug)) {
            throw new CatalogException('Destinations require a name and URL slug.');
        }
        if (mb_strlen($destination->name) > 180 || mb_strlen($destination->slug) > 180) {
            throw new CatalogException('The destination name or URL slug exceeds its supported length.');
        }
        if ($destination->type !== DestinationType::Continent && $destination->country_code === null) {
            throw new CatalogException('Country, region, city, and attraction destinations require an ISO country code.');
        }
        if ($destination->timezone !== null && ! in_array($destination->timezone, timezone_identifiers_list(), true)) {
            throw new CatalogException('Destination timezone must be a valid IANA timezone.');
        }
        if (($destination->latitude === null) xor ($destination->longitude === null)) {
            throw new CatalogException('Destination latitude and longitude must be supplied together.');
        }
        if ($destination->latitude !== null && ((float) $destination->latitude < -90 || (float) $destination->latitude > 90)) {
            throw new CatalogException('Destination latitude must be between -90 and 90.');
        }
        if ($destination->longitude !== null && ((float) $destination->longitude < -180 || (float) $destination->longitude > 180)) {
            throw new CatalogException('Destination longitude must be between -180 and 180.');
        }
    }

    /** Keep operational codes and slugs unique outside presentation validation. */
    private function assertUnique(Destination $destination): void
    {
        $slugExists = Destination::withTrashed()
            ->where('slug', $destination->slug)
            ->when($destination->exists, fn ($query) => $query->whereKeyNot($destination->getKey()))
            ->exists();
        if ($slugExists) {
            throw new CatalogException('Another destination already uses this URL slug.');
        }

        if ($destination->code !== null) {
            $codeExists = Destination::withTrashed()
                ->where('code', $destination->code)
                ->when($destination->exists, fn ($query) => $query->whereKeyNot($destination->getKey()))
                ->exists();
            if ($codeExists) {
                throw new CatalogException('Another destination already uses this operational code.');
            }
        }
    }

    /** Reject deactivation while publication, descendants, or public tours depend on this destination. */
    private function assertCanDeactivate(Destination $destination): void
    {
        if ($destination->status === PublicationStatus::Published) {
            throw new PublicationBlocked(['Unpublish this destination before making it inactive.']);
        }
        if ($destination->children()->where('is_active', true)->exists()) {
            throw new CatalogException('Make active child destinations inactive before hiding this destination.');
        }
        if ($destination->tours()->where('status', PublicationStatus::Published->value)->exists()) {
            throw new CatalogException('A destination assigned to published tours cannot be hidden.');
        }
    }

    /** Reject recursive, inactive, same-level, and geographically inconsistent parents. */
    private function assertParent(Destination $destination, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($destination->exists && $parentId === $destination->getKey()) {
            throw new HierarchyCycle('A destination cannot be its own parent.');
        }

        $visited = [];
        $currentId = $parentId;
        $immediateParent = null;
        while ($currentId !== null) {
            if (in_array($currentId, $visited, true) || ($destination->exists && $currentId === $destination->getKey())) {
                throw new HierarchyCycle('The selected parent would create a destination cycle.');
            }

            $visited[] = $currentId;
            $parent = Destination::query()->lockForUpdate()->find($currentId);
            if (! $parent instanceof Destination) {
                throw new CatalogException('The selected parent destination does not exist.');
            }
            if (! $parent->is_active) {
                throw new CatalogException('The selected parent destination must be active.');
            }
            $immediateParent ??= $parent;
            $currentId = $parent->parent_id;
        }

        if ($immediateParent instanceof Destination) {
            if (self::TYPE_RANK[$immediateParent->type->value] >= self::TYPE_RANK[$destination->type->value]) {
                throw new CatalogException('A destination parent must represent a broader geographic level.');
            }
            if ($immediateParent->country_code !== null && $destination->country_code !== $immediateParent->country_code) {
                throw new CatalogException('A child destination must use the same country code as its parent.');
            }
            if ($destination->status === PublicationStatus::Published && $immediateParent->status !== PublicationStatus::Published) {
                throw new PublicationBlocked(['A public destination requires a published parent destination.']);
            }
        }
    }

    /** Permit editorial draft-review changes while reserving public transitions for K2E. */
    private function assertEditorialTransition(PublicationStatus $from, PublicationStatus $to): void
    {
        if ($from === $to) {
            return;
        }
        if (! in_array($from, [PublicationStatus::Draft, PublicationStatus::Review], true)
            || ! in_array($to, [PublicationStatus::Draft, PublicationStatus::Review], true)) {
            throw new PublicationBlocked(['Publish, unpublish, and archive actions belong to the catalog publication workflow.']);
        }
    }

    /** Normalize an ISO alpha-2 country code. */
    private function countryCode(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[A-Z]{2}$/', $value) !== 1) {
            throw new CatalogException('Destination country code must be an ISO alpha-2 code.');
        }

        return $value;
    }

    /** Normalize an optional coordinate string. */
    private function coordinate(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Normalize bounded uppercase operational text. */
    private function uppercaseText(?string $value, int $maximum, string $label): ?string
    {
        $value = $this->optionalText($value, $maximum, $label);

        return $value === null ? null : strtoupper($value);
    }

    /** Trim bounded optional content without retaining whitespace-only values. */
    private function optionalText(?string $value, int $maximum, string $label): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) > $maximum) {
            throw new CatalogException("{$label} cannot exceed {$maximum} characters.");
        }

        return $value === '' ? null : $value;
    }
}
