<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Aggregate settlement state projected from completed payments. */
enum BookingPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
