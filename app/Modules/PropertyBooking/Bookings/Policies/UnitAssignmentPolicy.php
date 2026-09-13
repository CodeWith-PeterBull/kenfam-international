<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects exact-unit allocation records through booking management. */
final class UnitAssignmentPolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view unit assignments. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_AVAILABILITY;
    }

    /** Get the permission required to manage unit assignments. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }
}
