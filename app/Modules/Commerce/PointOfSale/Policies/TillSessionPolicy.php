<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Policies;

use App\Models\User;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Protects till visibility while reserving reconciliation for till managers.
 */
final class TillSessionPolicy
{
    /** Allow POS users and till managers to browse sessions. */
    public function viewAny(User $user): bool
    {
        return $this->canUseOrManage($user);
    }

    /** Allow POS users and till managers to inspect a session. */
    public function view(User $user, TillSession $session): bool
    {
        return $this->canUseOrManage($user);
    }

    /** Reserve session opening for till managers. */
    public function open(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_TILLS);
    }

    /** Reserve cash reconciliation and close for till managers. */
    public function close(User $user, TillSession $session): bool
    {
        return $user->can(CommercePermission::MANAGE_TILLS);
    }

    /** Determine whether the user can operate or administer sessions. */
    private function canUseOrManage(User $user): bool
    {
        return $user->can(CommercePermission::ACCESS_POS)
            || $user->can(CommercePermission::MANAGE_TILLS);
    }
}
