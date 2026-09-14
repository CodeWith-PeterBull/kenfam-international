<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled departure status vocabulary used by TravelTours. */
enum DepartureStatus: string
{
    use HasEnumLabel;
    case Draft = 'draft';
    case Open = 'open';
    case Guaranteed = 'guaranteed';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    case Departed = 'departed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
