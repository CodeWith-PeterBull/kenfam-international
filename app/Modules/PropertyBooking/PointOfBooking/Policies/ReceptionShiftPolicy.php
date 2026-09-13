<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects reception-shift visibility, ownership, opening, and reconciliation. */
final readonly class ReceptionShiftPolicy
{
    /** Create the reception shift policy with its required dependencies. */
    public function __construct(private PropertyAccessService $access) {}

    /** Determine whether the user may view the reception shift collection. */
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_SHIFTS)
            || $user->can(PropertyBookingPermission::ACCESS_POB);
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, ReceptionShift $shift): bool
    {
        return $this->viewAny($user)
            && $this->access->canAccess($user, $shift->property_id)
            && ($user->can(PropertyBookingPermission::MANAGE_SHIFTS) || $shift->receptionist_id === $user->getKey());
    }

    /** Determine whether the user may open the protected resource. */
    public function open(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_SHIFTS);
    }

    /** Determine whether the user may close the protected resource. */
    public function close(User $user, ReceptionShift $shift): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_SHIFTS)
            && $this->access->canAccess($user, $shift->property_id);
    }

    /** Determine whether the user may operate the protected resource. */
    public function operate(User $user, ReceptionShift $shift): bool
    {
        return $user->can(PropertyBookingPermission::ACCESS_POB)
            && $this->access->canAccess($user, $shift->property_id)
            && ($shift->receptionist_id === $user->getKey()
                || $user->can(PropertyBookingPermission::MANAGE_SHIFTS));
    }
}
