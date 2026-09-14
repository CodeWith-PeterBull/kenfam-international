<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled hold status vocabulary used by TravelTours. */
enum HoldStatus: string
{
    use HasEnumLabel;
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Released = 'released';
}
