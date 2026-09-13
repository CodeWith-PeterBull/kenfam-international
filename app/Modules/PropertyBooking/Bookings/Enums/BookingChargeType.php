<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Classification for non-accommodation booking charges. */
enum BookingChargeType: string
{
    case Service = 'service';
    case Fee = 'fee';
    case Adjustment = 'adjustment';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
