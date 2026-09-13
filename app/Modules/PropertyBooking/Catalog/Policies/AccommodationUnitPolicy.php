<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Support\Policies\PropertyScopedPolicy;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Database\Eloquent\Model;

/** Protects concrete inventory and delegates readiness-only updates. */
final class AccommodationUnitPolicy extends PropertyScopedPolicy
{
    /** Let housekeeping operators enter the readiness collection. */
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user)
            || $user->can(PropertyBookingPermission::MANAGE_READINESS);
    }

    /** Let housekeeping operators view assigned-property units. */
    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user)
            && $this->access->canAccess($user, (int) $model->getAttribute('property_id'));
    }

    /** Get the permission required to view accommodation units. */
    protected function viewPermission(): string
    {
        return PropertyBookingPermission::VIEW_PROPERTIES;
    }

    /** Get the permission required to manage accommodation units. */
    protected function managePermission(): string
    {
        return PropertyBookingPermission::MANAGE_PROPERTIES;
    }

    /** Determine whether the user may update the unit readiness state. */
    public function updateReadiness(User $user, AccommodationUnit $unit): bool
    {
        return $user->can(PropertyBookingPermission::MANAGE_READINESS)
            && $this->access->canAccess($user, $unit->property_id);
    }
}
