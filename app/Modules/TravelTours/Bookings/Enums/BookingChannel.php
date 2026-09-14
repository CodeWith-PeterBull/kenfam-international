<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled booking channel vocabulary used by TravelTours. */
enum BookingChannel: string
{
    use HasEnumLabel;
    case Web = 'web';
    case BookingDesk = 'booking_desk';
    case Admin = 'admin';
    case Agent = 'agent';
}
