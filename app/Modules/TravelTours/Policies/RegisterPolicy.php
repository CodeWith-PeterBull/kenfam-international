<?php

/**
 * Defines authorization rules for protected TravelTours resources.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Modules\TravelTours\Support\TravelToursPermission;

/** Booking-desk register authorization. */
final class RegisterPolicy extends TravelResourcePolicy
{
    /** Return the permission required to inspect this resource family. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::ACCESS_POB;
    }

    /** Return the permission required to mutate this resource family. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_SHIFTS;
    }
}
