<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Database\Eloquent\Builder;

/** Protects reusable guests through permission and related-booking property scope. */
final readonly class GuestPolicy
{
    /** Create the guest policy with its required dependencies. */
    public function __construct(private PropertyAccessService $access) {}

    /** Determine whether the user may view the guest collection. */
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyBookingPermission::VIEW_BOOKINGS)
            || $user->can(PropertyBookingPermission::MANAGE_GUESTS);
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, Guest $guest): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }
        if ($this->access->hasGlobalAccess($user)) {
            return true;
        }

        if ($user->can(PropertyBookingPermission::MANAGE_GUESTS)
            && $guest->created_by === $user->getKey()
            && ! $guest->bookingAssignments()->exists()) {
            return true;
        }

        $propertyIds = $this->access->assignedPropertyIds($user);

        return $guest->bookingAssignments()
            ->whereHas('booking', static fn (Builder $query): Builder => $query->whereIn('property_id', $propertyIds))
            ->exists();
    }

    /** Determine whether the user may create the protected resource. */
    public function create(User $user): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_GUESTS);
    }

    /** Determine whether the user may update the protected resource. */
    public function update(User $user, Guest $guest): bool
    {
        return $this->create($user) && $this->view($user, $guest);
    }

    /** Determine whether the user may delete the protected resource. */
    public function delete(User $user, Guest $guest): bool
    {
        return $this->update($user, $guest);
    }

    /** Determine whether the user may restore the protected resource. */
    public function restore(User $user, Guest $guest): bool
    {
        return $this->update($user, $guest);
    }
}
