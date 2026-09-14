<?php

/**
 * Typed highlight, inclusion, exclusion, requirement, or packing note.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Catalog\Enums\ContentItemType;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Typed highlight, inclusion, exclusion, requirement, or packing note. */
final class TourContentItem extends TravelToursModel
{
    protected $table = 'travel_tour_content_items';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => ContentItemType::class, 'sort_order' => 'integer'];
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
