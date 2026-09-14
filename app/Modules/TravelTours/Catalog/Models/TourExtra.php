<?php

/**
 * Optional bookable tour add-on priced in integer minor units.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Models\User;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Optional bookable tour add-on priced in integer minor units. */
final class TourExtra extends TravelToursModel
{
    use SoftDeletes;

    protected $table = 'travel_tour_extras';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['participant_types' => 'array', 'amount_minor' => 'integer', 'minimum_quantity' => 'integer', 'maximum_quantity' => 'integer', 'is_active' => 'boolean', 'is_public' => 'boolean', 'sort_order' => 'integer'];
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
     * Resolve the associated User record through created_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the associated User record through updated_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
