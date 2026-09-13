<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Storefront\Services\StorefrontNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adapts public catalog routes to module-owned storefront views.
 */
final class CatalogController extends Controller
{
    public function __construct(private readonly StorefrontNavigation $navigation) {}

    /**
     * Render the catalog shell; Livewire owns discovery state and pagination.
     */
    public function index(): View
    {
        return view('commerce::storefront.catalog.index', [
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }

    /**
     * Render one publicly visible product resolved by its readable slug.
     */
    public function show(Product $product): View
    {
        $product->loadMissing('category');
        abort_unless($product->isVisibleInStorefront(), 404);

        $product->loadMissing(['stock', 'media']);
        $relatedProducts = Product::query()
            ->visibleInStorefront()
            ->whereKeyNot($product->getKey())
            ->when(
                $product->category_id !== null,
                static fn (Builder $query): Builder => $query->where('category_id', $product->category_id),
            )
            ->with(['category', 'stock', 'media'])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        return view('commerce::storefront.catalog.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }
}
