<?php

/**
 * Append-only evidence for every booking lifecycle transition.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only evidence for every booking lifecycle transition. */
final class BookingStatusHistory extends TravelToursModel
{
    protected $table = 'travel_booking_status_history';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['previous_status' => BookingStatus::class, 'new_status' => BookingStatus::class, 'changed_at' => 'datetime'];
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
     * Resolve the associated User record through actor_id.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
