<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Protects reception endpoint visibility and configuration. */
final readonly class ReceptionRegisterPolicy
{
    /** Create the reception register policy with its required dependencies. */
    public function __construct(private PropertyAccessService $access) {}

    /** Determine whether the user may view the reception register collection. */
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_SHIFTS)
            || $user->can(PropertyBookingPermission::ACCESS_POB);
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, ReceptionRegister $register): bool
    {
        return $this->viewAny($user) && $this->access->canAccess($user, $register->property_id);
    }

    /** Determine whether the user may create the protected resource. */
    public function create(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_SHIFTS);
    }

    /** Determine whether the user may update the protected resource. */
    public function update(User $user, ReceptionRegister $register): bool
    {
        return $this->create($user) && $this->access->canAccess($user, $register->property_id);
    }

    /** Determine whether the user may delete the protected resource. */
    public function delete(User $user, ReceptionRegister $register): bool
    {
        return $this->update($user, $register);
    }

    /** Determine whether the user may restore the protected resource. */
    public function restore(User $user, ReceptionRegister $register): bool
    {
        return $this->update($user, $register);
    }
}
