<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Policies;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Support\CommercePermission;

/**
 * Applies the product-catalog permissions to category administration.
 */
final class ProductCategoryPolicy
{
    /** Allow administrative category-list access. */
    public function viewAny(User $user): bool
    {
        return $user->can(CommercePermission::VIEW_PRODUCTS);
    }

    /** Allow administrative category-detail access. */
    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can(CommercePermission::VIEW_PRODUCTS);
    }

    /** Allow category creation through ProductService. */
    public function create(User $user): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }

    /** Allow category hierarchy and presentation changes. */
    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }

    /** Allow deletion of an eligible category. */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can(CommercePermission::MANAGE_PRODUCTS);
    }
}
