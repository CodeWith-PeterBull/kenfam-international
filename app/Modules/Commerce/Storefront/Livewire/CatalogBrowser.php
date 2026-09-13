<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only public product discovery with durable URL-synchronized state.
 */
final class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'availability', except: 'all')]
    public string $availability = 'all';

    #[Url(as: 'price', except: 'all')]
    public string $priceRange = 'all';

    #[Url(as: 'sort', except: 'featured')]
    public string $sort = 'featured';

    protected string $paginationTheme = 'bootstrap';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedAvailability(): void
    {
        $this->resetPage();
    }

    public function updatedPriceRange(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Clear every discovery control and return to the first page.
     */
    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'availability', 'priceRange', 'sort');
        $this->resetPage();
    }

    /**
     * Return active categories represented in the public catalog.
     *
     * @return Collection<int, ProductCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->active()
            ->whereHas('products', static fn (Builder $products): Builder => $products->visibleInStorefront())
            ->withCount([
                'products as storefront_products_count' => static fn (Builder $products): Builder => $products->visibleInStorefront(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Return a paginated public result set with all card relationships loaded.
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $query = Product::query()
            ->visibleInStorefront()
            ->with(['category', 'stock', 'media']);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $matches) use ($search): void {
                $matches
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        $category = $this->validCategory();
        if ($category !== '') {
            $query->whereHas('category', static fn (Builder $categories): Builder => $categories->where('slug', $category));
        }

        $this->applyAvailability($query);
        [$minimum, $maximum] = $this->priceBounds();
        $query->whereEffectivePriceBetween($minimum, $maximum);
        $this->applySort($query);

        return $query->paginate(8);
    }

    /**
     * Return whether the current URL state narrows the default catalog.
     */
    #[Computed]
    public function hasFilters(): bool
    {
        return trim($this->search) !== ''
            || $this->validCategory() !== ''
            || $this->validAvailability() !== 'all'
            || $this->validPriceRange() !== 'all'
            || $this->validSort() !== 'featured';
    }

    public function render(): View
    {
        return view('commerce::livewire.storefront.catalog-browser');
    }

    private function applyAvailability(Builder $query): void
    {
        match ($this->validAvailability()) {
            'in-stock' => $query->where(function (Builder $available): void {
                $available
                    ->where('track_stock', false)
                    ->orWhereHas('stock', static fn (Builder $stock): Builder => $stock->where('on_hand', '>', 0));
            }),
            'out-of-stock' => $query
                ->where('track_stock', true)
                ->where(function (Builder $unavailable): void {
                    $unavailable
                        ->whereDoesntHave('stock')
                        ->orWhereHas('stock', static fn (Builder $stock): Builder => $stock->where('on_hand', '<=', 0));
                }),
            'on-sale' => $query->onSale(),
            default => null,
        };
    }

    private function applySort(Builder $query): void
    {
        match ($this->validSort()) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price-asc' => $query->orderByEffectivePrice()->orderBy('name'),
            'price-desc' => $query->orderByEffectivePrice('desc')->orderBy('name'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderBy('name'),
        };
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function priceBounds(): array
    {
        return match ($this->validPriceRange()) {
            'under-25000' => [null, 2_499_999],
            '25000-75000' => [2_500_000, 7_499_999],
            '75000-150000' => [7_500_000, 15_000_000],
            'above-150000' => [15_000_001, null],
            default => [null, null],
        };
    }

    private function validCategory(): string
    {
        $candidate = trim($this->category);

        return $candidate !== '' && $this->categories->contains('slug', $candidate) ? $candidate : '';
    }

    private function validAvailability(): string
    {
        return in_array($this->availability, ['all', 'in-stock', 'out-of-stock', 'on-sale'], true)
            ? $this->availability
            : 'all';
    }

    private function validPriceRange(): string
    {
        return in_array($this->priceRange, ['all', 'under-25000', '25000-75000', '75000-150000', 'above-150000'], true)
            ? $this->priceRange
            : 'all';
    }

    private function validSort(): string
    {
        return in_array($this->sort, ['featured', 'newest', 'price-asc', 'price-desc', 'name'], true)
            ? $this->sort
            : 'featured';
    }
}
