<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Originating channel for a booking. */
enum BookingChannel: string
{
    case Web = 'web';
    case PointOfBooking = 'pob';
    case Admin = 'admin';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return $this === self::PointOfBooking ? 'Point of booking' : str($this->value)->title()->toString();
    }
}
