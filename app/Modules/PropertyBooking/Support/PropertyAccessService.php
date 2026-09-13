<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Applies permission plus explicit-property scope to non-global operators. */
final class PropertyAccessService
{
    /** Determine whether the user may operate across all properties. */
    public function hasGlobalAccess(User $user): bool
    {
        return $user->is_active
            && ($user->isSystemAdministrator() || $user->can(PropertyBookingPermission::MANAGE_PROPERTIES));
    }

    /** Determine whether the user may operate within a specific property. */
    public function canAccess(User $user, Property|int $property): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $propertyId = $property instanceof Property ? $property->getKey() : $property;

        return DB::table('property_booking_property_user')
            ->where('property_id', $propertyId)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /** @return Collection<int, int> */
    public function assignedPropertyIds(User $user): Collection
    {
        if ($this->hasGlobalAccess($user)) {
            return Property::query()->pluck('id');
        }

        return DB::table('property_booking_property_user')
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_default')
            ->orderBy('property_id')
            ->pluck('property_id')
            ->map(static fn (mixed $id): int => (int) $id);
    }

    /** Restrict a property-owned query to the operator's explicit scope. */
    public function scope(Builder $query, User $user, string $qualifiedPropertyColumn = 'property_id'): Builder
    {
        if ($this->hasGlobalAccess($user)) {
            return $query;
        }

        return $query->whereIn($qualifiedPropertyColumn, $this->assignedPropertyIds($user));
    }

    /** @throws AuthorizationException */
    public function authorize(User $user, Property|int $property): void
    {
        if (! $this->canAccess($user, $property)) {
            throw new AuthorizationException('You are not assigned to this property.');
        }
    }
}
