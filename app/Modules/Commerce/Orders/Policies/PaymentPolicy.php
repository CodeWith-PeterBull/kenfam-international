<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Policies;

use App\Models\User;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Authorizes payment inspection and controlled recording separately.
 */
final class PaymentPolicy
{
    /** Allow access to payment lists. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_ORDERS);
    }

    /** Allow access to one payment record. */
    public function view(User $user, Payment $payment): bool
    {
        return $user->can(CommercePermission::VIEW_ORDERS);
    }

    /** Allow payment recording from order management or POS. */
    public function record(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_ORDERS)
            || $user->can(CommercePermission::ACCESS_POS);
    }
}
