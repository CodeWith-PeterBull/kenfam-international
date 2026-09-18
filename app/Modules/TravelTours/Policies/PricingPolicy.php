<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Modules\TravelTours\Support\TravelToursPermission;

/** Shared authorization for rate plans, participant fares, pricing rules, and promotions. */
final class PricingPolicy extends TravelResourcePolicy
{
    /** Return the permission required to inspect pricing records. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_PRICING;
    }

    /** Return the permission required to change pricing records. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_PRICING;
    }
}
