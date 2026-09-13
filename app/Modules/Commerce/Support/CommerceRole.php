<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Support;

use App\Models\User;

/**
 * Stable Commerce role identifiers and operator-facing labels.
 */
final class CommerceRole
{
    public const POS_CASHIER = 'pos-cashier';

    /**
     * Resolve the most relevant Commerce responsibility for an operator.
     */
    public static function operatorLabel(User $user): string
    {
        if ($user->isSystemAdministrator()) {
            return 'System administrator';
        }

        if ($user->can(CommercePermission::MANAGE_TILLS)) {
            return 'Till manager';
        }

        if ($user->can(CommercePermission::ACCESS_POS)) {
            return 'POS cashier';
        }

        return $user->user_type->label();
    }

    private function __construct() {}
}
