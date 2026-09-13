@php
    $accountUrl = auth()->check() ? route('dashboard') : route('login');
    $accountLabel = auth()->check() ? 'Workspace' : 'Account';
@endphp
<header class="commerce-header" data-commerce-header>
    <div class="commerce-header-desktop d-none d-xl-block">
        <div class="commerce-utility-bar">
            <div class="container-xxl commerce-utility-inner">
                <p><i data-lucide="package-check" aria-hidden="true"></i> Carefully selected products. Clear local pricing.</p>
                <nav aria-label="Store utility navigation">
                    <span>{{ config('commerce.currency.code', 'KES') }}</span>
                    <span><i data-lucide="shield-check" aria-hidden="true"></i> Secure catalog</span>
                    @if ($profile->primaryPhone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $profile->primaryPhone) }}">{{ $profile->primaryPhone }}</a>@endif
                </nav>
            </div>
        </div>

        <div class="commerce-primary-bar">
            <div class="container-xxl commerce-primary-grid">
                <nav class="commerce-primary-nav commerce-primary-nav--left" aria-label="Primary store navigation">
                    <a class="{{ request()->routeIs('commerce.storefront.catalog.*', 'commerce.storefront.products.*') ? 'active' : '' }}" href="{{ route('commerce.storefront.catalog.index') }}" @if (request()->routeIs('commerce.storefront.catalog.*', 'commerce.storefront.products.*')) aria-current="page" @endif>Shop</a>
                    <a href="{{ route('commerce.storefront.catalog.index', ['sort' => 'newest']) }}">New arrivals</a>
                    <a href="{{ route('commerce.storefront.catalog.index', ['sort' => 'featured']) }}">Featured</a>
                </nav>

                <a class="commerce-brand" href="{{ route('commerce.storefront.catalog.index') }}" aria-label="{{ $profile->name }} store home">
                    <img class="commerce-brand__logo commerce-brand__logo--default" src="{{ $brandLogo }}" alt="{{ $profile->name }}" width="220" height="64">
                    <img class="commerce-brand__logo commerce-brand__logo--dark" src="{{ $brandLogoLight }}" alt="" width="220" height="64" aria-hidden="true">
                    <span>Store</span>
                </a>

                <nav class="commerce-primary-nav commerce-primary-nav--right" aria-label="Store tools">
                    <a class="commerce-icon-button" href="{{ route('commerce.storefront.catalog.index') }}#catalog-search" aria-label="Search products" title="Search products">
                        <i data-lucide="search" aria-hidden="true"></i>
                    </a>
                    <livewire:commerce.storefront.cart-indicator key="cart-indicator-desktop" />
                    <a class="commerce-icon-button" href="{{ $accountUrl }}" aria-label="{{ $accountLabel }}" title="{{ $accountLabel }}">
                        <i data-lucide="user-round" aria-hidden="true"></i>
                    </a>
                    <button class="commerce-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
                </nav>
            </div>
        </div>

        @if ($navigationCategories->isNotEmpty())
            <nav class="commerce-category-bar" aria-label="Product categories">
                <div class="container-xxl">
                    @foreach ($navigationCategories as $category)
                        <a href="{{ route('commerce.storefront.catalog.index', ['category' => $category->slug]) }}">
                            {{ $category->name }}
                            <span>{{ $category->storefront_products_count }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>

    <div class="commerce-header-tablet d-none d-md-grid d-xl-none">
        <button class="commerce-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#commerceMobileMenu" aria-controls="commerceMobileMenu" aria-label="Open navigation" title="Menu">
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>
        <a class="commerce-brand" href="{{ route('commerce.storefront.catalog.index') }}" aria-label="{{ $profile->name }} store home">
            <img class="commerce-brand__logo commerce-brand__logo--default" src="{{ $brandLogo }}" alt="{{ $profile->name }}" width="220" height="64"><img class="commerce-brand__logo commerce-brand__logo--dark" src="{{ $brandLogoLight }}" alt="" width="220" height="64" aria-hidden="true"><span>Store</span>
        </a>
        <div class="commerce-compact-tools">
            <a class="commerce-icon-button" href="{{ route('commerce.storefront.catalog.index') }}#catalog-search" aria-label="Search products" title="Search products"><i data-lucide="search" aria-hidden="true"></i></a>
            <livewire:commerce.storefront.cart-indicator key="cart-indicator-tablet" />
            <a class="commerce-icon-button" href="{{ $accountUrl }}" aria-label="{{ $accountLabel }}" title="{{ $accountLabel }}"><i data-lucide="user-round" aria-hidden="true"></i></a>
            <button class="commerce-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="commerce-header-mobile d-grid d-md-none">
        <button class="commerce-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#commerceMobileMenu" aria-controls="commerceMobileMenu" aria-label="Open navigation" title="Menu">
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>
        <a class="commerce-brand" href="{{ route('commerce.storefront.catalog.index') }}" aria-label="{{ $profile->name }} store home">
            <img class="commerce-brand__logo commerce-brand__logo--default" src="{{ $brandLogo }}" alt="{{ $profile->name }}" width="220" height="64"><img class="commerce-brand__logo commerce-brand__logo--dark" src="{{ $brandLogoLight }}" alt="" width="220" height="64" aria-hidden="true"><span>Store</span>
        </a>
        <div class="commerce-compact-tools"><livewire:commerce.storefront.cart-indicator key="cart-indicator-mobile" /><button class="commerce-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button></div>
    </div>
</header>

<aside class="offcanvas offcanvas-start commerce-mobile-menu" tabindex="-1" id="commerceMobileMenu" aria-labelledby="commerceMobileMenuTitle">
    <div class="offcanvas-header commerce-mobile-menu__header">
        <a class="commerce-brand" href="{{ route('commerce.storefront.catalog.index') }}" id="commerceMobileMenuTitle" aria-label="{{ $profile->name }} store home">
            <img class="commerce-brand__logo commerce-brand__logo--default" src="{{ $brandLogo }}" alt="{{ $profile->name }}" width="220" height="64"><img class="commerce-brand__logo commerce-brand__logo--dark" src="{{ $brandLogoLight }}" alt="" width="220" height="64" aria-hidden="true"><span>Store</span>
        </a>
        <button class="commerce-icon-button" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation" title="Close">
            <i data-lucide="x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="offcanvas-body commerce-mobile-menu__body">
        <nav class="commerce-mobile-nav" aria-label="Mobile store navigation">
            <a href="{{ route('commerce.storefront.catalog.index') }}">Shop all <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('commerce.storefront.catalog.index', ['sort' => 'newest']) }}">New arrivals <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('commerce.storefront.catalog.index', ['sort' => 'featured']) }}">Featured <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('commerce.storefront.cart.index') }}">Your cart <i data-lucide="shopping-bag" aria-hidden="true"></i></a>

            @if ($navigationCategories->isNotEmpty())
                <div class="commerce-mobile-nav__group">
                    <button type="button" data-bs-toggle="collapse" data-bs-target="#commerceMobileCategories" aria-expanded="true" aria-controls="commerceMobileCategories">
                        <span>Categories</span><i data-lucide="chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="collapse show" id="commerceMobileCategories">
                        @foreach ($navigationCategories as $category)
                            <a href="{{ route('commerce.storefront.catalog.index', ['category' => $category->slug]) }}">
                                <span>{{ $category->name }}</span><small>{{ $category->storefront_products_count }}</small>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="{{ $accountUrl }}">{{ $accountLabel }} <i data-lucide="user-round" aria-hidden="true"></i></a>
        </nav>

        <div class="commerce-mobile-menu__meta">
            @if ($profile->primaryEmail)<a href="mailto:{{ $profile->primaryEmail }}">{{ $profile->primaryEmail }}</a>@endif
            @if ($profile->primaryPhone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $profile->primaryPhone) }}">{{ $profile->primaryPhone }}</a>@endif
            @if ($profile->address())<p>{{ $profile->address() }}</p>@endif
        </div>
    </div>
</aside>
