<?php

/**
 * Defines authorization rules for protected TravelTours resources.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Models\User;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Database\Eloquent\Model;

/** Non-destructive booking lifecycle authorization. */
final class BookingPolicy extends TravelResourcePolicy
{
    /** Return the permission required to inspect this resource family. */
    protected function viewPermission(): string
    {
        return TravelToursPermission::VIEW_BOOKINGS;
    }

    /** Return the permission required to mutate this resource family. */
    protected function managePermission(): string
    {
        return TravelToursPermission::MANAGE_BOOKINGS;
    }

    /** Determine whether the user may record payment evidence against a booking. */
    public function recordPayment(User $user, Model $model): bool
    {
        return $user->can(TravelToursPermission::MANAGE_PAYMENTS);
    }

    /** Determine whether the user may confirm or reject recorded payments. */
    public function confirmPayment(User $user, Model $model): bool
    {
        return $user->can(TravelToursPermission::CONFIRM_PAYMENTS);
    }

    /** Determine whether the user may record a refund. */
    public function refund(User $user, Model $model): bool
    {
        return $user->can(TravelToursPermission::REFUND_PAYMENTS);
    }

    /** Determine whether the user may archive this resource. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /** Prevent destructive deletion where retained operational history is required. */
    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
