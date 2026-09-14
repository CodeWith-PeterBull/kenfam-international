<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled destination type vocabulary used by TravelTours. */
enum DestinationType: string
{
    use HasEnumLabel;
    case Continent = 'continent';
    case Country = 'country';
    case Region = 'region';
    case City = 'city';
    case Attraction = 'attraction';
}
