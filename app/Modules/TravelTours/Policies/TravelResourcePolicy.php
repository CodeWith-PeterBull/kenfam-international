<?php

/**
 * Defines authorization rules for protected TravelTours resources.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Least-privilege policy foundation for travel resources. */
abstract class TravelResourcePolicy
{
    /** Return the permission required to inspect this resource family. */
    abstract protected function viewPermission(): string;

    /** Return the permission required to mutate this resource family. */
    abstract protected function managePermission(): string;

    /** Determine whether the user may browse this resource family. */
    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission());
    }

    /** Determine whether the user may inspect the requested resource. */
    public function view(User $user, Model $model): bool
    {
        return $user->can($this->viewPermission());
    }

    /** Determine whether the user may create this resource. */
    public function create(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    /** Determine whether the user may change this resource. */
    public function update(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    /** Determine whether the user may archive this resource. */
    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    /** Determine whether the user may restore an archived resource. */
    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    /** Prevent destructive deletion where retained operational history is required. */
    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
