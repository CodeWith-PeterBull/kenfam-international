<?php

/**
 * Immutable evidence that a promotion was consumed by a booking.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable evidence that a promotion was consumed by a booking. */
final class PromotionRedemption extends TravelToursModel
{
    protected $table = 'travel_promotion_redemptions';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_applied_minor' => 'integer', 'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Resolve the associated Promotion record.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Resolve the associated TourBooking record.
     *
     * @return BelongsTo<TourBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class);
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
}
