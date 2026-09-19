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

/**
 * Shift managers open, close, and sign off shifts for any operator; a desk
 * operator sees and works only the shift opened for them.
 */
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

    /** Determine whether the user may browse shifts: managers and desk operators. */
    public function viewAny(User $user): bool
    {
        return $user->can(TravelToursPermission::MANAGE_SHIFTS) || $user->can(TravelToursPermission::ACCESS_POB);
    }

    /** Determine whether the user may inspect the requested shift. */
    public function view(User $user, Model $model): bool
    {
        return $user->can(TravelToursPermission::MANAGE_SHIFTS) || ($model instanceof BookingShift && $model->operator_id === $user->id);
    }

    /** Opening a shift for an operator is a manager's act. */
    public function open(User $user): bool
    {
        return $user->can(TravelToursPermission::MANAGE_SHIFTS);
    }

    /** Closing a shift against the counted cash is a manager's act. */
    public function close(User $user, BookingShift $shift): bool
    {
        return $user->can(TravelToursPermission::MANAGE_SHIFTS);
    }

    /** Operating the terminal on a shift needs desk access and either ownership or shift management. */
    public function operate(User $user, BookingShift $shift): bool
    {
        return $user->can(TravelToursPermission::ACCESS_POB)
            && ($shift->operator_id === $user->id || $user->can(TravelToursPermission::MANAGE_SHIFTS));
    }
}
