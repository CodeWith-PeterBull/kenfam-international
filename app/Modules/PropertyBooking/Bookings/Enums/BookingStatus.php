<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Reservation lifecycle independent of stay and payment state. */
enum BookingStatus: string
{
    case Held = 'held';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Completed = 'completed';
    case Expired = 'expired';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** Determine whether the status reserves concrete-unit availability. */
    public function consumesAvailability(): bool
    {
        return in_array($this, [self::Held, self::Pending, self::Confirmed], true);
    }

    /** Determine whether the booking lifecycle has reached a terminal status. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::NoShow, self::Completed, self::Expired], true);
    }
}
