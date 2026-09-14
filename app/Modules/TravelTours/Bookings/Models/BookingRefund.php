<?php

/**
 * Non-destructive refund record linked to booking and source payment.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\RefundStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Non-destructive refund record linked to booking and source payment. */
final class BookingRefund extends TravelToursModel
{
    protected $table = 'travel_booking_refunds';

    /** Hide idempotency and processor correlation internals from routine serialization. */
    protected $hidden = ['operation_key', 'transaction_identifier', 'safe_metadata'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'status' => RefundStatus::class, 'requested_at' => 'datetime',
            'completed_at' => 'datetime', 'safe_metadata' => 'array',
        ];
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
     * Resolve the associated User record through processed_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function processorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Resolve the user who requested the refund.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
