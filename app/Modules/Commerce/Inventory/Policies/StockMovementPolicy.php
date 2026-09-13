<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Policies;

use App\Models\User;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Exposes immutable inventory history only to inventory viewers.
 */
final class StockMovementPolicy
{
    /** Allow access to the immutable movement history. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_INVENTORY);
    }

    /** Allow access to one immutable movement record. */
    public function view(User $user, StockMovement $movement): bool
    {
        return $user->can(CommercePermission::VIEW_INVENTORY);
    }
}
