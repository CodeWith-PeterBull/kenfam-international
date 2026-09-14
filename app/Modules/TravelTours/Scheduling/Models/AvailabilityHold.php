<?php

/**
 * Expiring, quote-bound reservation of departure capacity.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Models;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Expiring, quote-bound reservation of departure capacity. */
final class AvailabilityHold extends TravelToursModel
{
    protected $table = 'travel_availability_holds';

    /** Hold ownership and quote internals must not leak through model JSON. */
    protected $hidden = ['owner_token_hash', 'operation_key', 'email_hash', 'quote_snapshot'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adult_count' => 'integer', 'child_count' => 'integer', 'infant_count' => 'integer', 'seat_count' => 'integer',
            'quoted_total_minor' => 'integer', 'quote_snapshot' => 'array', 'status' => HoldStatus::class,
            'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    /**
     * Resolve the associated TourDeparture record.
     *
     * @return BelongsTo<TourDeparture, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class);
    }

    /**
     * Resolve the associated TravelCustomer record.
     *
     * @return BelongsTo<TravelCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(TravelCustomer::class);
    }

    /**
     * Query related TourBooking records through availability_hold_id.
     *
     * @return HasOne<TourBooking, $this>
     */
    public function booking(): HasOne
    {
        return $this->hasOne(TourBooking::class, 'availability_hold_id');
    }

    /**
     * Apply the active selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', HoldStatus::Active->value)->where('expires_at', '>', now());
    }
}
