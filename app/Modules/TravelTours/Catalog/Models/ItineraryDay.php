<?php

/**
 * Ordered day-level itinerary narrative.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ordered day-level itinerary narrative. */
final class ItineraryDay extends TravelToursModel
{
    protected $table = 'travel_itinerary_days';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day_number' => 'integer', 'meals' => 'array', 'sort_order' => 'integer'];
    }

    /**
     * Resolve the associated Tour record.
     *
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Resolve the associated Destination record through start_destination_id.
     *
     * @return BelongsTo<Destination, $this>
     */
    public function startDestination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'start_destination_id');
    }

    /**
     * Resolve the associated Destination record through end_destination_id.
     *
     * @return BelongsTo<Destination, $this>
     */
    public function endDestination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'end_destination_id');
    }

    /**
     * Query related ItineraryActivity records.
     *
     * @return HasMany<ItineraryActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ItineraryActivity::class)->orderBy('sequence');
    }
}
