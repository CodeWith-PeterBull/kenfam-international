<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Authorizes global amenity-vocabulary maintenance. */
final class AmenityPolicy
{
    /** Determine whether the user may view the amenity collection. */
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyBookingPermission::VIEW_PROPERTIES);
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, Amenity $amenity): bool
    {
        return $this->viewAny($user);
    }

    /** Determine whether the user may create the protected resource. */
    public function create(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_PROPERTIES);
    }

    /** Determine whether the user may update the protected resource. */
    public function update(User $user, Amenity $amenity): bool
    {
        return $this->create($user);
    }

    /** Determine whether the user may delete the protected resource. */
    public function delete(User $user, Amenity $amenity): bool
    {
        return $this->create($user);
    }
}
