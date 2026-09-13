<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Authorizes property visibility and reserves portfolio maintenance globally. */
final readonly class PropertyPolicy
{
    /** Create the property policy with its required dependencies. */
    public function __construct(private PropertyAccessService $access) {}

    /** Determine whether the user may view the property collection. */
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyBookingPermission::VIEW_PROPERTIES);
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, Property $property): bool
    {
        return $this->viewAny($user) && $this->access->canAccess($user, $property);
    }

    /** Determine whether the user may create the protected resource. */
    public function create(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_PROPERTIES);
    }

    /** Determine whether the user may update the protected resource. */
    public function update(User $user, Property $property): bool
    {
        return $this->create($user);
    }

    /** Determine whether the user may delete the protected resource. */
    public function delete(User $user, Property $property): bool
    {
        return $this->create($user);
    }

    /** Determine whether the user may restore the protected resource. */
    public function restore(User $user, Property $property): bool
    {
        return $this->create($user);
    }
}
