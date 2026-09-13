<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Enums;

/** Public lifecycle for a sellable accommodation type. */
enum UnitTypeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
