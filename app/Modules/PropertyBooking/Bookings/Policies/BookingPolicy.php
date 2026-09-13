<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects reservation lifecycle operations by capability and property scope. */
final class BookingPolicy extends PropertyScopedPolicy
{
    /** Get the permission required to view bookings. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_BOOKINGS;
    }

    /** Get the permission required to manage bookings. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }

    /** Determine whether the user may check in the protected resource. */
    public function checkIn(User $user, Booking $booking): bool
    {
        return $user->can(PropertyBookingPermission::CHECK_IN)
            && $this->access->canAccess($user, $booking->property_id);
    }

    /** Determine whether the user may check out the protected resource. */
    public function checkOut(User $user, Booking $booking): bool
    {
        return $user->can(PropertyBookingPermission::CHECK_OUT)
            && $this->access->canAccess($user, $booking->property_id);
    }
}
