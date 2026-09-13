<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Enums;

/** Administrative lifecycle for a rate plan. */
enum RatePlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
