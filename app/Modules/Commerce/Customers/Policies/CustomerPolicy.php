<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Customers\Policies;

use App\Models\User;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Protects reusable customer records independently from public checkout data.
 */
final class CustomerPolicy
{
    /** Allow access to the reusable customer directory. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_CUSTOMERS);
    }

    /** Allow access to one reusable customer record. */
    public function view(User $user, Customer $customer): bool
    {
        return $user->can(CommercePermission::MANAGE_CUSTOMERS);
    }

    /** Allow creation of a reusable customer record. */
    public function create(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_CUSTOMERS);
    }

    /** Allow changes through CustomerService. */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can(CommercePermission::MANAGE_CUSTOMERS);
    }

    /** Allow a reusable customer record to be archived. */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(CommercePermission::MANAGE_CUSTOMERS);
    }
}
