<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects immutable stay snapshots through the booking aggregate boundary. */
final class BookingStayPolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view booking stays. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_BOOKINGS;
    }

    /** Get the permission required to manage booking stays. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }
}
