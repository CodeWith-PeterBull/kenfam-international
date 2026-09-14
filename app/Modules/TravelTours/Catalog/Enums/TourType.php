<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled tour type vocabulary used by TravelTours. */
enum TourType: string
{
    use HasEnumLabel;
    case Escorted = 'escorted';
    case Private = 'private';
    case Group = 'group';
    case Educational = 'educational';
    case Pilgrimage = 'pilgrimage';
    case Adventure = 'adventure';
    case Safari = 'safari';
    case Custom = 'custom';
}
