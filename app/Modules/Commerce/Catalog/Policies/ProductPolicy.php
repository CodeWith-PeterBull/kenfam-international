<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Policies;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Authorizes administrative product visibility and lifecycle mutations.
 */
final class ProductPolicy
{
    /** Allow administrative product-list access. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_PRODUCTS);
    }

    /** Allow administrative product-detail access. */
    public function view(User $user, Product $product): bool
    {
        return $user->can(CommercePermission::VIEW_PRODUCTS);
    }

    /** Allow product creation through a validated management boundary. */
    public function create(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }

    /** Allow an existing product to be changed through ProductService. */
    public function update(User $user, Product $product): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }

    /** Allow a product to be soft deleted. */
    public function delete(User $user, Product $product): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }

    /** Allow a soft-deleted product to be restored. */
    public function restore(User $user, Product $product): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }
}
