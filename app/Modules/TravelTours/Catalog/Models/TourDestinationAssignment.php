<?php

/**
 * Explicit ordered tour-destination assignment.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\Concerns\HasTravelToursFactory;
use App\Modules\TravelTours\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Explicit ordered tour-destination assignment. */
final class TourDestinationAssignment extends Pivot
{
    use HasTravelToursFactory;
    use HasUlid;

    protected $table = 'travel_tour_destination';

    public $incrementing = true;

    protected $guarded = ['id', 'ulid'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'is_overnight' => 'boolean'];
    }
}
