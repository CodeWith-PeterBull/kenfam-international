<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Policies;

use App\Models\User;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Separates inventory visibility from stock-changing authority.
 */
final class StockPolicy
{
    /** Allow access to current stock projections. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_INVENTORY);
    }

    /** Allow access to one product stock projection. */
    public function view(User $user, Stock $stock): bool
    {
        return $user->can(CommercePermission::VIEW_INVENTORY);
    }

    /** Allow a signed adjustment through InventoryService. */
    public function adjust(User $user, Stock $stock): bool
    {
        return $user->can(CommercePermission::MANAGE_INVENTORY);
    }
}
