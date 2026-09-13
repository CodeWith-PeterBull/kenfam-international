<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support\Policies;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Shared permission and property-scope mechanics for module aggregate policies. */
abstract class PropertyScopedPolicy
{
    /** Create the property scoped policy with its required dependencies. */
    public function __construct(protected readonly PropertyAccessService $access) {}

    /** Get the permission required to view the aggregate. */
    abstract protected function viewPermission(): string;

    /** Get the permission required to manage the aggregate. */
    abstract protected function managePermission(): string;

    /** Determine whether the user may view this aggregate collection. */
    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission()) || $user->can($this->managePermission());
    }

    /** Determine whether the user may view the protected resource. */
    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user) && $this->access->canAccess($user, $this->propertyId($model));
    }

    /** Determine whether the user may create the protected resource. */
    public function create(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    /** Determine whether the user may update the protected resource. */
    public function update(User $user, Model $model): bool
    {
        return $user->can($this->managePermission()) && $this->access->canAccess($user, $this->propertyId($model));
    }

    /** Determine whether the user may delete the protected resource. */
    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    /** Determine whether the user may restore the protected resource. */
    public function restore(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    /** Resolve the property identifier used for scope checks. */
    protected function propertyId(Model $model): int
    {
        $direct = $model->getAttribute('property_id');
        if ($direct !== null) {
            return (int) $direct;
        }

        return match (true) {
            $model instanceof RateOverride => (int) $model->ratePlan->property_id,
            $model instanceof BookingStay => (int) $model->booking->property_id,
            $model instanceof BookingCharge, $model instanceof BookingPayment => (int) $model->booking->property_id,
            default => throw new LogicException('The policy model does not expose a property ownership path.'),
        };
    }
}
