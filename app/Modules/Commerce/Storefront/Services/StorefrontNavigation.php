<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Catalog\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Provides one shared public category-navigation query for storefront pages.
 */
final class StorefrontNavigation
{
    /**
     * Return active root categories that currently contain public products.
     *
     * @return Collection<int, ProductCategory>
     */
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->active()
            ->root()
            ->whereHas('products', static fn (Builder $products): Builder => $products->visibleInStorefront())
            ->withCount([
                'products as storefront_products_count' => static fn (Builder $products): Builder => $products->visibleInStorefront(),
            ])
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
