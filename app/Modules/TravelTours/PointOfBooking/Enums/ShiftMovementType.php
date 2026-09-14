<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled shift movement type vocabulary used by TravelTours. */
enum ShiftMovementType: string
{
    use HasEnumLabel;
    case OpeningFloat = 'opening_float';
    case Payment = 'payment';
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
    case Refund = 'refund';
}
