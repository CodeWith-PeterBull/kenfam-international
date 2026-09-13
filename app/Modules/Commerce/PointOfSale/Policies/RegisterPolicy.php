<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Policies;

use App\Models\User;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Protects register visibility and configuration.
 */
final class RegisterPolicy
{
    /** Allow POS users and till managers to browse registers. */
    public function viewAny(User $user): bool
    {
        return $this->canUseOrManage($user);
    }

    /** Allow POS users and till managers to inspect a register. */
    public function view(User $user, Register $register): bool
    {
        return $this->canUseOrManage($user);
    }

    /** Reserve register creation for till managers. */
    public function create(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_TILLS);
    }

    /** Reserve register configuration for till managers. */
    public function update(User $user, Register $register): bool
    {
        return $user->can(CommercePermission::MANAGE_TILLS);
    }

    /** Reserve register deletion for till managers. */
    public function delete(User $user, Register $register): bool
    {
        return $user->can(CommercePermission::MANAGE_TILLS);
    }

    /** Determine whether the user can operate or administer registers. */
    private function canUseOrManage(User $user): bool
    {
        return $user->can(CommercePermission::ACCESS_POS)
            || $user->can(CommercePermission::MANAGE_TILLS);
    }
}
