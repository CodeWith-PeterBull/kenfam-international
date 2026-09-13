<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Compensating lifecycle for an additional booking charge. */
enum BookingChargeStatus: string
{
    case Posted = 'posted';
    case Voided = 'voided';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
