<?php

/**
 * Append-only financial movement contributing to shift reconciliation.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\BookingRefund;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only financial movement contributing to shift reconciliation. */
final class BookingShiftMovement extends TravelToursModel
{
    protected $table = 'travel_booking_shift_movements';

    /** Hide idempotency keys from routine movement serialization. */
    protected $hidden = ['operation_key'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['movement_type' => ShiftMovementType::class, 'amount_minor' => 'integer', 'occurred_at' => 'datetime'];
    }

    /**
     * Resolve the associated BookingShift record.
     *
     * @return BelongsTo<BookingShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(BookingShift::class);
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
     * Resolve the associated BookingPayment record.
     *
     * @return BelongsTo<BookingPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class);
    }

    /**
     * Resolve the associated BookingRefund record.
     *
     * @return BelongsTo<BookingRefund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(BookingRefund::class);
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
