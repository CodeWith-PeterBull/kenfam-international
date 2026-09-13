@php
    $productUrl = route('commerce.storefront.products.show', ['product' => $product->slug]);
    $gallery = $product->getMedia('product_gallery');
    $primaryImage = $gallery->first()?->getUrl() ?: asset('aureon/assets/brand/logo-icon.png');
    $storeProfile = app(\App\Contracts\ResolvesInstitutionProfile::class)->current();
    $storeContact = \App\Modules\Commerce\Support\InstitutionContact::from($storeProfile);
    $discount = $product->saleDiscountPercentage();

    $currencyDecimals = (int) config('commerce.currency.decimal_places', 2);
    $currencyCode = (string) config('commerce.currency.code', 'KES');
    $priceDecimal = \App\Modules\Commerce\Support\ScaledDecimal::formatUnsigned((int) $product->effective_price_minor, $currencyDecimals);
    $priceLabel = \App\Modules\Commerce\Support\MoneyFormatter::format((int) $product->effective_price_minor);
    $galleryUrls = $gallery->isEmpty() ? [$primaryImage] : $gallery->map(fn ($media) => $media->getUrl())->values()->all();

    $whatsappOrderUrl = ($storeProfile && config('commerce.storefront.whatsapp_order.enabled', true))
        ? $storeContact->whatsappOrderUrl($product->name, $productUrl, $priceLabel)
        : null;

    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'image' => $galleryUrls,
        'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($product->meta_description ?: $product->short_description ?: $product->description)), 300),
        'sku' => $product->sku,
        'brand' => ['@type' => 'Brand', 'name' => $storeProfile->shortName],
        'offers' => [
            '@type' => 'Offer',
            'url' => $productUrl,
            'priceCurrency' => $currencyCode,
            'price' => $priceDecimal,
            'availability' => $product->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', 'name' => $storeProfile->name],
        ],
    ];
@endphp
@extends('commerce::layouts.storefront')

@section('title', $product->meta_title ?: $product->name)
@section('meta_description', $product->meta_description ?: $product->short_description)
@section('canonical', $productUrl)
@section('social_image', $primaryImage)
@section('og_type', 'product')
@section('nav', 'shop')

@push('head')
    <meta property="product:price:amount" content="{{ $priceDecimal }}">
    <meta property="product:price:currency" content="{{ $currencyCode }}">
    <meta property="og:availability" content="{{ $product->isInStock() ? 'instock' : 'outofstock' }}">
    <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <section class="commerce-product-detail">
        <div class="container-xxl">
            <nav class="commerce-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('commerce.storefront.catalog.index') }}">Shop</a><i data-lucide="chevron-right" aria-hidden="true"></i>
                @if ($product->category)<a href="{{ route('commerce.storefront.catalog.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a><i data-lucide="chevron-right" aria-hidden="true"></i>@endif
                <span aria-current="page">{{ $product->name }}</span>
            </nav>

            <div class="commerce-product-detail__grid">
                <div class="commerce-product-gallery" data-product-gallery data-gallery-count="{{ count($galleryUrls) }}">
                    <div class="commerce-product-gallery__main" data-gallery-main>
                        <div class="swiper commerce-gallery-swiper">
                            <div class="swiper-wrapper">
                                @foreach ($galleryUrls as $index => $imageUrl)
                                    <div class="swiper-slide">
                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}{{ $index === 0 ? '' : ' image '.($index + 1) }}" width="900" height="900" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if ($discount !== null)<span class="commerce-product-gallery__badge">-{{ $discount }}%</span>@endif
                        <button type="button" class="commerce-gallery-expand" data-gallery-expand aria-label="View images full screen">
                            <i data-lucide="maximize-2" aria-hidden="true"></i>
                        </button>
                        @if (count($galleryUrls) > 1)
                            <button type="button" class="commerce-gallery-nav commerce-gallery-nav--prev" data-gallery-prev aria-label="Previous image"><i data-lucide="chevron-left" aria-hidden="true"></i></button>
                            <button type="button" class="commerce-gallery-nav commerce-gallery-nav--next" data-gallery-next aria-label="Next image"><i data-lucide="chevron-right" aria-hidden="true"></i></button>
                            <div class="commerce-gallery-pagination" data-gallery-pagination aria-hidden="true"></div>
                        @endif
                    </div>
                    @if (count($galleryUrls) > 1)
                        <div class="commerce-product-gallery__thumbs" role="tablist" aria-label="Product images" data-gallery-thumbs>
                            @foreach ($galleryUrls as $index => $imageUrl)
                                <button type="button" class="commerce-gallery-thumb {{ $loop->first ? 'is-active' : '' }}" data-gallery-thumb="{{ $index }}" role="tab" aria-label="Show image {{ $index + 1 }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                    <img src="{{ $imageUrl }}" alt="" width="160" height="160" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="commerce-product-summary">
                    <div class="commerce-product-summary__category">
                        @if ($product->category)<a href="{{ route('commerce.storefront.catalog.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>@endif
                        @if ($product->is_featured)<span><i data-lucide="sparkles" aria-hidden="true"></i>Featured</span>@endif
                    </div>
                    <h1>{{ $product->name }}</h1>
                    <p class="commerce-product-summary__sku">SKU {{ $product->sku }}</p>
                    <div class="commerce-product-summary__price">
                        <strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->effective_price_minor) }}</strong>
                        @if ($product->isOnSale())<del>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->price_minor) }}</del><span>Save {{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->price_minor - $product->sale_price_minor) }}</span>@endif
                    </div>
                    <p class="commerce-product-summary__lead">{{ $product->short_description }}</p>

                    <div class="commerce-product-availability {{ $product->isInStock() ? 'is-available' : 'is-unavailable' }}">
                        <i data-lucide="{{ $product->isInStock() ? 'circle-check' : 'circle-x' }}" aria-hidden="true"></i>
                        <div>
                            <strong>{{ $product->isInStock() ? 'Available' : 'Currently unavailable' }}</strong>
                            <span>
                                @if ($product->track_stock && $product->isInStock())
                                    {{ number_format($product->stock?->on_hand ?? 0) }} in stock
                                @else
                                    {{ $product->track_stock ? 'Check back for availability' : 'Stock available on request' }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <dl class="commerce-product-facts">
                        <div><dt>Minimum order</dt><dd>{{ $product->minimum_order_quantity }} {{ \Illuminate\Support\Str::plural($product->unit_label, $product->minimum_order_quantity) }}</dd></div>
                        <div><dt>Tax</dt><dd>{{ $product->is_tax_inclusive ? 'Included' : 'Calculated separately' }}</dd></div>
                        <div><dt>Fulfilment</dt><dd>Pickup or delivery</dd></div>
                    </dl>

                    <div class="commerce-product-actions">
                        @if ($product->isInStock())
                            <livewire:commerce.storefront.add-to-cart
                                :product-id="$product->id"
                                :minimum="$product->minimum_order_quantity"
                                :maximum="$product->maximum_order_quantity"
                                :compact="false"
                                :key="'product-detail-cart-'.$product->id"
                            />
                        @endif
                        @if ($whatsappOrderUrl)
                            <a class="commerce-button commerce-button--whatsapp" href="{{ $whatsappOrderUrl }}" target="_blank" rel="noopener noreferrer">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="18" height="18"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35M12.04 21.5h-.01a9.4 9.4 0 0 1-4.79-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.38 9.38 0 0 1-1.44-5.01c0-5.18 4.22-9.4 9.42-9.4a9.36 9.36 0 0 1 9.4 9.41c0 5.18-4.22 9.4-9.4 9.4M20.52 3.49A11.8 11.8 0 0 0 12.04 0C5.46 0 .1 5.36.09 11.94c0 2.1.55 4.16 1.6 5.97L0 24l6.24-1.64a11.9 11.9 0 0 0 5.8 1.48h.01c6.58 0 11.94-5.36 11.95-11.95a11.9 11.9 0 0 0-3.48-8.4"/></svg>
                                Buy via WhatsApp
                            </a>
                        @endif
                        @if ($storeProfile->primaryEmail)
                            <a class="commerce-button commerce-button--secondary" href="mailto:{{ $storeProfile->primaryEmail }}?subject={{ rawurlencode('Product enquiry: '.$product->name) }}"><i data-lucide="mail" aria-hidden="true"></i>Request details</a>
                        @endif
                        <a class="commerce-button commerce-button--secondary" href="{{ route('commerce.storefront.catalog.index') }}"><i data-lucide="arrow-left" aria-hidden="true"></i>Continue shopping</a>
                    </div>

                    @include('commerce::storefront.partials.share', ['url' => $productUrl, 'title' => $product->name])

                    <div class="commerce-product-assurances">
                        <span><i data-lucide="badge-check" aria-hidden="true"></i><small>Clear pricing</small></span>
                        <span><i data-lucide="package-check" aria-hidden="true"></i><small>Pickup ready</small></span>
                        <span><i data-lucide="messages-square" aria-hidden="true"></i><small>Human support</small></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="commerce-product-information" aria-labelledby="product-information-title">
        <div class="container-xxl commerce-product-information__grid">
            <div><p class="commerce-eyebrow">Product overview</p><h2 id="product-information-title">Designed around practical use.</h2></div>
            <div class="accordion commerce-product-accordion" id="productInformationAccordion">
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#productDescription" aria-expanded="true" aria-controls="productDescription">Description</button></h3>
                    <div id="productDescription" class="accordion-collapse collapse show" data-bs-parent="#productInformationAccordion"><div class="accordion-body">{{ $product->description }}</div></div>
                </div>
                @if (filled($product->specifications))
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#productSpecifications" aria-expanded="false" aria-controls="productSpecifications">Specifications</button></h3>
                        <div id="productSpecifications" class="accordion-collapse collapse" data-bs-parent="#productInformationAccordion">
                            <div class="accordion-body commerce-specifications">
                                @foreach ($product->specifications as $group => $items)
                                    <section><h4>{{ $group }}</h4><dl>@foreach ($items as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#productFulfilment" aria-expanded="false" aria-controls="productFulfilment">Fulfilment and support</button></h3>
                    <div id="productFulfilment" class="accordion-collapse collapse" data-bs-parent="#productInformationAccordion"><div class="accordion-body">Pickup and flat-fee delivery options are supported by the Aureon Commerce order engine. Final availability and delivery timing are confirmed during order placement.</div></div>
                </div>
            </div>
        </div>
    </section>

    @if ($relatedProducts->isNotEmpty())
        <section class="commerce-related-products" aria-labelledby="related-products-title">
            <div class="container-xxl">
                <div class="commerce-section-heading"><div><p class="commerce-eyebrow">Continue exploring</p><h2 id="related-products-title">Related products</h2></div><a href="{{ route('commerce.storefront.catalog.index', $product->category ? ['category' => $product->category->slug] : []) }}">View category <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
                <div class="commerce-product-grid">@foreach ($relatedProducts as $relatedProduct)@include('commerce::storefront.catalog.partials.product-card', ['product' => $relatedProduct])@endforeach</div>
            </div>
        </section>
    @endif
@endsection
