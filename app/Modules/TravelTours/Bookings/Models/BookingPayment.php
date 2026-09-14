<?php

/**
 * Idempotent provider or manually confirmed booking payment.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Idempotent provider or manually confirmed booking payment. */
final class BookingPayment extends TravelToursModel
{
    protected $table = 'travel_booking_payments';

    /** Hide idempotency and provider correlation internals from routine serialization. */
    protected $hidden = ['operation_key', 'transaction_identifier', 'safe_metadata'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'amount_minor' => 'integer', 'status' => PaymentRecordStatus::class, 'paid_at' => 'datetime', 'safe_metadata' => 'array'];
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
     * Resolve the associated PaymentSchedule record.
     *
     * @return BelongsTo<PaymentSchedule, $this>
     */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
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
     * Resolve the associated User record through received_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Query related BookingRefund records through payment_id.
     *
     * @return HasMany<BookingRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'payment_id');
    }
}
