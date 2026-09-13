<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Enums;

/** Lifecycle for an exact physical-unit allocation. */
enum UnitAssignmentStatus: string
{
    case Active = 'active';
    case Released = 'released';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
