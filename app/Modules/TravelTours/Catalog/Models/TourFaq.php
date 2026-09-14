<?php

/**
 * Ordered public question and answer attached to a tour.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ordered public question and answer attached to a tour. */
final class TourFaq extends TravelToursModel
{
    protected $table = 'travel_tour_faqs';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
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
}
