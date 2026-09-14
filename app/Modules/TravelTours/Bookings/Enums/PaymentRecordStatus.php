<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled payment record status vocabulary used by TravelTours. */
enum PaymentRecordStatus: string
{
    use HasEnumLabel;
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
