<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled participant type vocabulary used by TravelTours. */
enum ParticipantType: string
{
    use HasEnumLabel;
    case Adult = 'adult';
    case Child = 'child';
    case Infant = 'infant';
}
