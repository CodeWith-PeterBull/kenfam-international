<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled pricing rule type vocabulary used by TravelTours. */
enum PricingRuleType: string
{
    use HasEnumLabel;
    case Seasonal = 'seasonal';
    case Group = 'group';
    case EarlyBird = 'early_bird';
}
