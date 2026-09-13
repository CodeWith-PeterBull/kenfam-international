<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Enums;

/** Determines where an amenity may be assigned. */
enum AmenityScope: string
{
    case Property = 'property';
    case Unit = 'unit';
    case Both = 'both';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
