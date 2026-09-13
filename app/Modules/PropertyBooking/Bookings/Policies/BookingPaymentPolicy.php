<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Policies;

use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects tender visibility and mutation by property scope. */
final class BookingPaymentPolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view booking payments. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_BOOKINGS;
    }

    /** Get the permission required to manage booking payments. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_PAYMENTS;
    }
}
