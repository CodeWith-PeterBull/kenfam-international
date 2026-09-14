<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled booking mode vocabulary used by TravelTours. */
enum BookingMode: string
{
    use HasEnumLabel;
    case Instant = 'instant';
    case Approval = 'approval';
}
