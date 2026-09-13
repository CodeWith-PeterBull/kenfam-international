<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Modules\PropertyBooking\Guests\Models\Guest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Occupant assignment pivot with primary-guest and presence state. */
final class BookingGuest extends Pivot
{
    protected $table = 'property_booking_guest_assignments';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'booking_id',
        'guest_id',
        'booking_stay_id',
        'is_primary',
        'primary_booking_guard',
        'checked_in_at',
        'checked_out_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    /** Get the booking relationship. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** Get the guest relationship. */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    /** Get the booking stay relationship. */
    public function bookingStay(): BelongsTo
    {
        return $this->belongsTo(BookingStay::class, 'booking_stay_id');
    }
}
