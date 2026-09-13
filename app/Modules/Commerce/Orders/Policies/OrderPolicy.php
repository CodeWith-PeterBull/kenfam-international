<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Policies;

use App\Models\User;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Protects administrative order visibility and lifecycle operations.
 */
final class OrderPolicy
{
    /** Allow access to web and POS order lists. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_ORDERS);
    }

    /** Allow access to one order aggregate. */
    public function view(User $user, Order $order): bool
    {
        return $user->can(CommercePermission::VIEW_ORDERS);
    }

    /** Allow eligible administrative lifecycle mutations. */
    public function update(User $user, Order $order): bool
    {
        return $user->can(CommercePermission::MANAGE_ORDERS);
    }

    /** Allow eligible unpaid web-order cancellation. */
    public function cancel(User $user, Order $order): bool
    {
        return $user->can(CommercePermission::MANAGE_ORDERS);
    }
}
