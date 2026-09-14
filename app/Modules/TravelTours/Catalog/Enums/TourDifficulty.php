<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled tour difficulty vocabulary used by TravelTours. */
enum TourDifficulty: string
{
    use HasEnumLabel;
    case Easy = 'easy';
    case Moderate = 'moderate';
    case Active = 'active';
    case Challenging = 'challenging';
}
