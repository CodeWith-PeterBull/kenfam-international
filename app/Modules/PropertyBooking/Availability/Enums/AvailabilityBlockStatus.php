<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Enums;

/** Lifecycle for an operational availability block. */
enum AvailabilityBlockStatus: string
{
    case Active = 'active';
    case Released = 'released';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
