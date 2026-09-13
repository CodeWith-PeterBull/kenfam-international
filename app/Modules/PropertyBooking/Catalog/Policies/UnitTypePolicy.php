<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects unit-type visibility and maintenance by property scope. */
final class UnitTypePolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view unit types. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_PROPERTIES;
    }

    /** Get the permission required to manage unit types. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_PROPERTIES;
    }
}
