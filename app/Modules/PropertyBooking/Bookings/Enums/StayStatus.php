<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Guest-presence lifecycle for a booking. */
enum StayStatus: string
{
    case Expected = 'expected';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case NoShow = 'no_show';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
