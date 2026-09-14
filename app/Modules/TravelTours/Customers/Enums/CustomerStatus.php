<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled customer status vocabulary used by TravelTours. */
enum CustomerStatus: string
{
    use HasEnumLabel;
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
}
