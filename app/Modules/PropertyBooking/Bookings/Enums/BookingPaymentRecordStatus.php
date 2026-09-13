<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Lifecycle for an individual tender record. */
enum BookingPaymentRecordStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
