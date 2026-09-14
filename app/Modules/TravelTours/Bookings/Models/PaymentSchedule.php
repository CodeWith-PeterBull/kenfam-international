<?php

/**
 * Due-date and funding state for one booking instalment.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Modules\TravelTours\Bookings\Enums\PaymentScheduleStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Due-date and funding state for one booking instalment. */
final class PaymentSchedule extends TravelToursModel
{
    protected $table = 'travel_payment_schedules';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['instalment_number' => 'integer', 'due_date' => 'date', 'expected_amount_minor' => 'integer', 'paid_amount_minor' => 'integer', 'status' => PaymentScheduleStatus::class];
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
     * Query related BookingPayment records.
     *
     * @return HasMany<BookingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }
}
