<?php

/**
 * Explicit tour-category assignment with primary and ordering semantics.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\Concerns\HasTravelToursFactory;
use App\Modules\TravelTours\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Explicit tour-category assignment with primary and ordering semantics. */
final class TourCategoryAssignment extends Pivot
{
    use HasTravelToursFactory;
    use HasUlid;

    protected $table = 'travel_tour_category';

    public $incrementing = true;

    protected $guarded = ['id', 'ulid'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'sort_order' => 'integer'];
    }
}
