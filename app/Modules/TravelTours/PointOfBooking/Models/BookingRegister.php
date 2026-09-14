<?php

/**
 * Configurable booking-desk endpoint and receipt-printer policy.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Configurable booking-desk endpoint and receipt-printer policy. */
final class BookingRegister extends TravelToursModel
{
    protected $table = 'travel_booking_registers';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'receipt_paper_width_mm' => 'integer', 'automatic_receipt_print' => 'boolean', 'printer_options' => 'array'];
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

    /**
     * Query related BookingShift records through register_id.
     *
     * @return HasMany<BookingShift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(BookingShift::class, 'register_id');
    }

    /**
     * Query related TourBooking records through register_id.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class, 'register_id');
    }

    /**
     * Apply the active selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
