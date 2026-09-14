<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled shift status vocabulary used by TravelTours. */
enum ShiftStatus: string
{
    use HasEnumLabel;
    case Open = 'open';
    case Closed = 'closed';
    case Reconciled = 'reconciled';
}
