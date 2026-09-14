<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled booking status vocabulary used by TravelTours. */
enum BookingStatus: string
{
    use HasEnumLabel;
    case Held = 'held';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';

    /** Determine whether this booking state permits no further ordinary progression. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::Completed], true);
    }

    /** Determine whether bookings in this state consume departure capacity. */
    public function reservesCapacity(): bool
    {
        return in_array($this, [self::Held, self::Pending, self::Confirmed], true);
    }
}
