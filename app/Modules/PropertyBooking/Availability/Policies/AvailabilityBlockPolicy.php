<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects property-calendar blocks and their release lifecycle. */
final class AvailabilityBlockPolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view availability blocks. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_AVAILABILITY;
    }

    /** Get the permission required to manage availability blocks. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_AVAILABILITY;
    }
}
