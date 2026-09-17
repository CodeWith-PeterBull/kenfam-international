<?php

/** Owns typed tour identity and base-content writes. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Data\TourData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\PublicationBlocked;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/** Keep tour validation and actor attribution inside one transaction. */
final readonly class TourService
{
    /** Inject the database transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Create an unpublished tour with the validated base fields. */
    public function create(TourData $data, User $actor): Tour
    {
        if ($data->status !== PublicationStatus::Draft) {
            throw new PublicationBlocked(['New tours must start as drafts.']);
        }

        return $this->database->transaction(function () use ($data, $actor): Tour {
            $tour = new Tour;
            $tour->forceFill($this->payload($data));
            $tour->forceFill(['status' => PublicationStatus::Draft, 'published_at' => null, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->assertValid($tour);
            $this->assertUnique($tour);
            $tour->save();

            return $tour->refresh();
        });
    }

    /** Update base metadata without changing the publication lifecycle. */
    public function update(Tour $tour, TourData $data, User $actor): Tour
    {
        return $this->database->transaction(function () use ($tour, $data, $actor): Tour {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tour->id);
            if ($data->status !== $tour->status) {
                throw new PublicationBlocked(['Tour publication state changes belong to the publication workflow.']);
            }
            $tour->forceFill($this->payload($data));
            $tour->forceFill(['updated_by' => $actor->id]);
            $this->assertValid($tour);
            $this->assertUnique($tour);
            $tour->save();

            return $tour->refresh();
        });
    }

    /** Map immutable input to declared catalog columns only. */
    private function payload(TourData $data): array
    {
        $name = trim($data->name);
        $languages = array_values(array_unique(array_map(trim(...), $data->languages)));

        return [
            'code' => strtoupper(trim($data->code)), 'slug' => Str::slug($data->slug ?: $name),
            'type' => $data->type, 'name' => $name,
            'tagline' => $this->optional($data->tagline, 240, 'Tagline'),
            'short_description' => $this->optional($data->shortDescription, 360, 'Short description'),
            'description' => $this->optional($data->description, 50_000, 'Tour description'),
            'duration_days' => $data->durationDays, 'duration_nights' => $data->durationNights,
            'minimum_age' => $data->minimumAge, 'difficulty' => $data->difficulty,
            'minimum_participants' => $data->minimumParticipants,
            'maximum_participants' => $data->maximumParticipants,
            'languages' => $languages, 'booking_mode' => $data->bookingMode,
            'meeting_point_name' => $this->optional($data->meetingPointName, 200, 'Meeting point'),
            'meeting_point_details' => $this->optional($data->meetingPointDetails, 10_000, 'Meeting details'),
            'meeting_latitude' => $this->coordinate($data->meetingLatitude),
            'meeting_longitude' => $this->coordinate($data->meetingLongitude),
            'end_point_name' => $this->optional($data->endPointName, 200, 'End point'),
            'end_point_details' => $this->optional($data->endPointDetails, 10_000, 'End details'),
            'end_latitude' => $this->coordinate($data->endLatitude),
            'end_longitude' => $this->coordinate($data->endLongitude),
            'terms' => $this->optional($data->terms, 50_000, 'Terms'),
            'cancellation_summary' => $this->optional($data->cancellationSummary, 10_000, 'Cancellation summary'),
            'policy_version' => $this->optional($data->policyVersion, 60, 'Policy version'),
            'is_featured' => $data->isFeatured, 'sort_order' => $data->sortOrder,
            'meta_title' => $this->optional($data->metaTitle, 160, 'SEO title'),
            'meta_description' => $this->optional($data->metaDescription, 320, 'SEO description'),
        ];
    }

    /** Reject incoherent tour content even for non-Livewire callers. */
    private function assertValid(Tour $tour): void
    {
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{1,39}$/', (string) $tour->code) !== 1
            || blank($tour->name) || mb_strlen($tour->name) > 200 || blank($tour->slug) || mb_strlen($tour->slug) > 180) {
            throw new CatalogException('A tour requires a valid code, name, and URL slug.');
        }
        if ($tour->duration_days < 1 || $tour->duration_days > 365 || $tour->duration_nights < 0 || $tour->duration_nights > $tour->duration_days
            || $tour->minimum_age < 0 || $tour->minimum_age > 120) {
            throw new CatalogException('Tour duration, nights, or minimum age is outside the supported range.');
        }
        if ($tour->exists && ItineraryDay::query()->where('tour_id', $tour->id)->where('day_number', '>', $tour->duration_days)->exists()) {
            throw new CatalogException('Tour duration cannot be shorter than its existing itinerary.');
        }
        if ($tour->minimum_participants < 1 || $tour->minimum_participants > 1000
            || ($tour->maximum_participants !== null && ($tour->maximum_participants < $tour->minimum_participants || $tour->maximum_participants > 1000))) {
            throw new CatalogException('Participant bounds are inconsistent.');
        }
        if ($tour->sort_order < 0 || $tour->sort_order > 4294967295) {
            throw new CatalogException('Tour sort order is outside the supported range.');
        }
        if (count($tour->languages ?? []) > 20 || collect($tour->languages)->contains(fn ($language) => ! is_string($language) || preg_match('/^[\pL -]{2,60}$/u', $language) !== 1)) {
            throw new CatalogException('Tour languages must be a bounded list of readable names.');
        }
        $this->assertCoordinatePair($tour->meeting_latitude, $tour->meeting_longitude, 'Meeting point');
        $this->assertCoordinatePair($tour->end_latitude, $tour->end_longitude, 'End point');
        if ($tour->status === PublicationStatus::Published && (blank($tour->short_description) || blank($tour->description))) {
            throw new PublicationBlocked(['A published tour must retain its public descriptions.']);
        }
    }

    /** Keep operational codes and public slugs globally unique among retained rows. */
    private function assertUnique(Tour $tour): void
    {
        foreach (['code', 'slug'] as $field) {
            if (Tour::withTrashed()->where($field, $tour->{$field})->when($tour->exists, fn ($query) => $query->whereKeyNot($tour->id))->exists()) {
                throw new CatalogException("Another tour already uses this {$field}.");
            }
        }
    }

    /** Validate a paired WGS84 coordinate range. */
    private function assertCoordinatePair(?string $latitude, ?string $longitude, string $label): void
    {
        if (($latitude === null) xor ($longitude === null)) {
            throw new CatalogException("{$label} latitude and longitude must be supplied together.");
        }
        if ($latitude !== null && (! is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90)) {
            throw new CatalogException("{$label} latitude must be between -90 and 90.");
        }
        if ($longitude !== null && (! is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180)) {
            throw new CatalogException("{$label} longitude must be between -180 and 180.");
        }
    }

    /** Normalize optional coordinates before model casts. */
    private function coordinate(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Normalize and bound optional editorial text. */
    private function optional(?string $value, int $limit, string $label): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) > $limit) {
            throw new CatalogException("{$label} cannot exceed {$limit} characters.");
        }

        return $value === '' ? null : $value;
    }
}
