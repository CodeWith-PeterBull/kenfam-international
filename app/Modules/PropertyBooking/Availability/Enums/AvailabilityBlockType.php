<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Enums;

/** Reason category for taking a concrete unit out of inventory. */
enum AvailabilityBlockType: string
{
    case Maintenance = 'maintenance';
    case OwnerUse = 'owner_use';
    case DeepCleaning = 'deep_cleaning';
    case Administrative = 'administrative';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
