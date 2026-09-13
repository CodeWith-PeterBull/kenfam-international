<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects posted and voided booking charges by property scope. */
final class BookingChargePolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view booking charges. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_BOOKINGS;
    }

    /** Get the permission required to manage booking charges. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }
}
