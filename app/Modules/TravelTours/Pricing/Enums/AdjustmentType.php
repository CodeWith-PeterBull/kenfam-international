<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled adjustment type vocabulary used by TravelTours. */
enum AdjustmentType: string
{
    use HasEnumLabel;
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Override = 'override';
}
