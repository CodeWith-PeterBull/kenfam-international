<?php

/**
 * Defines authorization rules for protected TravelTours resources.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Database\Eloquent\Model;

/** Booking-desk shift ownership and supervision authorization. */
final class ShiftPolicy extends TravelResourcePolicy
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

    /** Determine whether the user may inspect the requested resource. */
    public function view(User $user, Model $model): bool
    {
        return $user->can(TravelToursPermission::MANAGE_SHIFTS) || ($model instanceof BookingShift && $model->operator_id === $user->id);
    }
}
