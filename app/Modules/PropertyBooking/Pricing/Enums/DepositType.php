<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Enums;

/** Deposit calculation strategy for a rate plan. */
enum DepositType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
