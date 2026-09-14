<?php

/**
 * Scheduled or optional activity within an itinerary day.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Scheduled or optional activity within an itinerary day. */
final class ItineraryActivity extends TravelToursModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'travel_itinerary_activities';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_included' => 'boolean', 'is_optional' => 'boolean', 'sequence' => 'integer'];
    }

    /** Declare allowed collections, MIME types and storage visibility for this resource. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('itinerary_media')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->useDisk('public');
    }

    /**
     * Resolve the associated ItineraryDay record.
     *
     * @return BelongsTo<ItineraryDay, $this>
     */
    public function itineraryDay(): BelongsTo
    {
        return $this->belongsTo(ItineraryDay::class);
    }

    /**
     * Resolve the associated Destination record.
     *
     * @return BelongsTo<Destination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
