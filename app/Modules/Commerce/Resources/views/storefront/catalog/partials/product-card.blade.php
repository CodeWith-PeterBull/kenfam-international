@php
    $productUrl = route('commerce.storefront.products.show', ['product' => $product->slug]);
    $imageUrl = $product->getFirstMediaUrl('product_gallery') ?: asset('aureon/assets/brand/logo-icon.png');
    $discount = $product->saleDiscountPercentage();
    $inStock = $product->isInStock();
@endphp
<article class="commerce-product-card" wire:key="storefront-product-{{ $product->id }}">
    <a class="commerce-product-card__media" href="{{ $productUrl }}" aria-label="View {{ $product->name }}">
        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="640" height="640" loading="lazy">
        @if ($discount !== null)<span class="commerce-product-card__sale">-{{ $discount }}%</span>@endif
        @if ($product->is_featured)<span class="commerce-product-card__featured"><i data-lucide="sparkles" aria-hidden="true"></i>Featured</span>@endif
    </a>
    <div class="commerce-product-card__body">
        <div class="commerce-product-card__meta">
            @if ($product->category)
                <a href="{{ route('commerce.storefront.catalog.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>
            @else
                <span>General</span>
            @endif
            <span class="{{ $inStock ? 'is-available' : 'is-unavailable' }}">{{ $inStock ? 'In stock' : 'Out of stock' }}</span>
        </div>
        <h3><a href="{{ $productUrl }}">{{ $product->name }}</a></h3>
        <div class="commerce-product-card__price">
            <strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->effective_price_minor) }}</strong>
            @if ($product->isOnSale())<del>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->price_minor) }}</del>@endif
        </div>
        <div class="commerce-product-card__actions">
            <a class="commerce-product-card__link" href="{{ $productUrl }}">View product <i data-lucide="arrow-right" aria-hidden="true"></i></a>
            @if ($inStock)
                <livewire:commerce.storefront.add-to-cart
                    :product-id="$product->id"
                    :minimum="$product->minimum_order_quantity"
                    :maximum="$product->maximum_order_quantity"
                    :compact="true"
                    :key="'product-card-cart-'.$product->id"
                />
            @endif
        </div>
    </div>
</article>
