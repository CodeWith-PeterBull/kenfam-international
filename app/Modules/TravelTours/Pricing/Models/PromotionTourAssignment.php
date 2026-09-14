<?php

/**
 * Inclusion or exclusion of a tour from a promotion.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Support\Concerns\HasTravelToursFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Inclusion or exclusion of a tour from a promotion. */
final class PromotionTourAssignment extends Pivot
{
    use HasTravelToursFactory;

    protected $table = 'travel_promotion_tour';

    protected $guarded = ['id'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_exclusion' => 'boolean'];
    }
}
