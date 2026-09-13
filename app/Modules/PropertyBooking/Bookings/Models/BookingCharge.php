<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeType;
use App\Modules\PropertyBooking\Database\Factories\BookingChargeFactory;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Additional service, fee, or adjustment line with compensating void state. */
final class BookingCharge extends Model
{
    /** @use HasFactory<BookingChargeFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_charges';

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => BookingChargeType::class, 'status' => BookingChargeStatus::class,
            'quantity' => 'integer', 'unit_amount_minor' => 'integer', 'subtotal_minor' => 'integer',
            'tax_rate_bps' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer',
            'is_tax_inclusive' => 'boolean', 'posted_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): BookingChargeFactory
    {
        return BookingChargeFactory::new();
    }

    /** Get the booking relationship. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** Get the booking stay relationship. */
    public function bookingStay(): BelongsTo
    {
        return $this->belongsTo(BookingStay::class, 'booking_stay_id');
    }

    /** Get the reception shift relationship. */
    public function receptionShift(): BelongsTo
    {
        return $this->belongsTo(ReceptionShift::class, 'reception_shift_id');
    }

    /** Get the poster relationship. */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Get the voider relationship. */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Constrain the query to posted records. */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', BookingChargeStatus::Posted->value);
    }
}
