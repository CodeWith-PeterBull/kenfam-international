@extends('commerce::layouts.storefront')

@section('title', 'Shop')
@section('meta_description', 'Discover featured products, new arrivals, and current offers across the Aureon Commerce catalog.')
@section('canonical', route('commerce.storefront.catalog.index'))
@section('nav', 'shop')

@section('content')
    <section class="commerce-catalog-hero" aria-labelledby="catalog-title">
        <div class="container-xxl commerce-catalog-hero__grid">
            <div class="commerce-catalog-hero__content">
                <nav class="commerce-breadcrumb commerce-breadcrumb--light" aria-label="Breadcrumb"><span>Home</span><i data-lucide="chevron-right" aria-hidden="true"></i><span aria-current="page">Shop</span></nav>
                <p class="commerce-eyebrow">Aureon Commerce</p>
                <h1 id="catalog-title">Shop</h1>
                <p>Useful products, clear pricing, and a focused path from discovery to decision.</p>
            </div>
            <div class="commerce-catalog-hero__media" aria-hidden="true">
                @foreach ($storefrontNavigationCategories->take(3) as $category)
                    <span><img src="{{ $category->getFirstMediaUrl('category_image') ?: asset('aureon/assets/brand/logo-icon.png') }}" alt="" width="420" height="420"></span>
                @endforeach
            </div>
        </div>
    </section>

    @if ($storefrontNavigationCategories->isNotEmpty())
        <section class="commerce-category-section" aria-labelledby="category-title">
            <div class="container-xxl">
                <div class="commerce-section-heading"><div><p class="commerce-eyebrow">Browse directly</p><h2 id="category-title">Product categories</h2></div><a href="#storefront-catalog">View complete catalog <i data-lucide="arrow-down" aria-hidden="true"></i></a></div>
                <div class="commerce-category-grid">
                    @foreach ($storefrontNavigationCategories as $category)
                        <a class="commerce-category-tile" href="{{ route('commerce.storefront.catalog.index', ['category' => $category->slug]) }}">
                            <span class="commerce-category-tile__media"><img src="{{ $category->getFirstMediaUrl('category_image') ?: asset('aureon/assets/brand/logo-icon.png') }}" alt="" width="320" height="320" loading="lazy"></span>
                            <span><strong>{{ $category->name }}</strong><small>{{ $category->storefront_products_count }} {{ \Illuminate\Support\Str::plural('product', $category->storefront_products_count) }}</small></span>
                            <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <livewire:commerce.storefront.catalog-browser />
@endsection
