<?php

/**
 * Operator-owned booking-desk session with cash reconciliation.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Operator-owned booking-desk session with cash reconciliation. */
final class BookingShift extends TravelToursModel
{
    protected $table = 'travel_booking_shifts';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class, 'register_open_guard' => 'integer', 'operator_open_guard' => 'integer',
            'currency_exponent' => 'integer', 'opening_float_minor' => 'integer', 'expected_cash_minor' => 'integer',
            'actual_cash_minor' => 'integer', 'variance_minor' => 'integer', 'opened_at' => 'datetime',
            'closed_at' => 'datetime', 'reconciled_at' => 'datetime',
        ];
    }

    /**
     * Resolve the associated BookingRegister record.
     *
     * @return BelongsTo<BookingRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(BookingRegister::class);
    }

    /**
     * Resolve the associated User record through operator_id.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Resolve the associated User record through reconciled_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * Query related BookingShiftMovement records through shift_id.
     *
     * @return HasMany<BookingShiftMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BookingShiftMovement::class, 'shift_id');
    }

    /**
     * Query related TourBooking records through shift_id.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class, 'shift_id');
    }

    /**
     * Query related BookingPayment records through shift_id.
     *
     * @return HasMany<BookingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'shift_id');
    }

    /**
     * Apply the open selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::Open->value);
    }
}
