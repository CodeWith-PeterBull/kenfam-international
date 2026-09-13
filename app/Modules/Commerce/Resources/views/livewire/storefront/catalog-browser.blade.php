<section class="commerce-catalog" id="storefront-catalog" aria-labelledby="catalog-results-title">
    <div class="container-xxl">
        <div class="commerce-catalog-controls">
            <div class="commerce-search-field" id="catalog-search">
                <label for="storefront-search">Search products</label>
                <span><i data-lucide="search" aria-hidden="true"></i><input id="storefront-search" type="search" wire:model.live.debounce.350ms="search" placeholder="Name, SKU, or product detail" autocomplete="off"></span>
            </div>
            <div class="commerce-filter-field">
                <label for="storefront-category">Category</label>
                <select id="storefront-category" wire:model.live="category">
                    <option value="">All categories</option>
                    @foreach ($this->categories as $category)<option value="{{ $category->slug }}">{{ $category->name }} ({{ $category->storefront_products_count }})</option>@endforeach
                </select>
            </div>
            <div class="commerce-filter-field">
                <label for="storefront-availability">Availability</label>
                <select id="storefront-availability" wire:model.live="availability">
                    <option value="all">All products</option><option value="in-stock">In stock</option><option value="out-of-stock">Out of stock</option><option value="on-sale">On sale</option>
                </select>
            </div>
            <div class="commerce-filter-field">
                <label for="storefront-price">Price</label>
                <select id="storefront-price" wire:model.live="priceRange">
                    <option value="all">Any price</option><option value="under-25000">Under KSh 25,000</option><option value="25000-75000">KSh 25,000 - 75,000</option><option value="75000-150000">KSh 75,000 - 150,000</option><option value="above-150000">Above KSh 150,000</option>
                </select>
            </div>
        </div>

        <div class="commerce-results-bar">
            <div><p class="commerce-eyebrow">Catalog</p><h2 id="catalog-results-title">{{ number_format($this->products->total()) }} {{ \Illuminate\Support\Str::plural('product', $this->products->total()) }}</h2></div>
            <div class="commerce-results-tools">
                <span class="commerce-live-status" wire:loading.delay aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Updating</span>
                @if ($this->hasFilters)<button class="commerce-icon-button" type="button" wire:click="clearFilters" aria-label="Clear catalog filters" title="Clear filters"><i data-lucide="list-restart" aria-hidden="true"></i></button>@endif
                <label for="storefront-sort" class="visually-hidden">Sort products</label>
                <select id="storefront-sort" wire:model.live="sort"><option value="featured">Featured first</option><option value="newest">Newest</option><option value="price-asc">Price: low to high</option><option value="price-desc">Price: high to low</option><option value="name">Name</option></select>
            </div>
        </div>

        <div class="commerce-product-grid" wire:loading.class="commerce-product-grid--loading">
            @forelse ($this->products as $product)
                @include('commerce::storefront.catalog.partials.product-card', ['product' => $product])
            @empty
                <div class="commerce-catalog-empty">
                    <span><i data-lucide="package-search" aria-hidden="true"></i></span><h3>No matching products</h3><p>Try a broader search or clear the active catalog filters.</p><button class="commerce-button commerce-button--secondary" type="button" wire:click="clearFilters"><i data-lucide="list-restart" aria-hidden="true"></i>Clear filters</button>
                </div>
            @endforelse
        </div>

        @if ($this->products->hasPages())
            <div class="commerce-pagination">{{ $this->products->onEachSide(1)->links(data: ['scrollTo' => '#storefront-catalog']) }}</div>
        @endif
    </div>
</section>
