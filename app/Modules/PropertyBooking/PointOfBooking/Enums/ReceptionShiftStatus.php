<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Enums;

/** Reception shift lifecycle. */
enum ReceptionShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
