<?php

/**
 * Staff responsibility and leadership assignment for one departure.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Models;

use App\Models\User;
use App\Modules\TravelTours\Support\Concerns\HasTravelToursFactory;
use App\Modules\TravelTours\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Staff responsibility and leadership assignment for one departure. */
final class DepartureStaffAssignment extends Pivot
{
    use HasTravelToursFactory;
    use HasUlid;

    protected $table = 'travel_departure_staff';

    public $incrementing = true;

    protected $guarded = ['id', 'ulid'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_lead' => 'boolean'];
    }

    /** Resolve the assigned operator for the scheduling workspace. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
