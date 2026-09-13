<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects bounded rate overrides by property scope. */
final class RateOverridePolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view rate overrides. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_RATES;
    }

    /** Get the permission required to manage rate overrides. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_RATES;
    }
}
