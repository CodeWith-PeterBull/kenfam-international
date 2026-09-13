<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Enums;

/** Supported short-stay billing units. */
enum StayPricingUnit: string
{
    case Hour = 'hour';
    case DayUse = 'day_use';
    case Night = 'night';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
