@php
    $workspaceUrl = auth()->check() ? route('dashboard') : route('login');
    $workspaceLabel = auth()->check() ? 'Workspace' : 'Sign in';
    $homeActive = request()->routeIs('home');
    $toursActive = request()->routeIs('travel-tours.storefront.catalog.*', 'travel-tours.storefront.tours.*');
@endphp

<header class="travel-header" data-travel-header>
    <div class="travel-header__desktop d-none d-xl-block">
        <div class="travel-utility-bar">
            <div class="container-xxl">
                <p>
                    <i data-lucide="compass" aria-hidden="true"></i>
                    Thoughtfully coordinated journeys
                    @if ($travelProfile->foundedYear)
                        since {{ $travelProfile->foundedYear }}
                    @endif
                </p>
                <nav aria-label="Travel utility navigation">
                    <span>{{ config('travel-tours.defaults.currency', 'KES') }}</span>
                    @if ($travelProfile->email)
                        <a href="mailto:{{ $travelProfile->email }}">{{ $travelProfile->email }}</a>
                    @endif
                    @if ($travelProfile->phoneLink)
                        <a href="tel:{{ $travelProfile->phoneLink }}">{{ $travelProfile->phone }}</a>
                    @endif
                </nav>
            </div>
        </div>

        <div class="travel-primary-nav">
            <div class="container-xxl travel-primary-nav__grid">
                <nav class="travel-primary-nav__links" aria-label="Primary navigation">
                    <a class="{{ $homeActive ? 'active' : '' }}" href="{{ route('home') }}" aria-current="{{ $homeActive ? 'page' : 'false' }}">Home</a>
                    <a class="{{ $toursActive ? 'active' : '' }}" href="{{ route('travel-tours.storefront.catalog.index') }}" aria-current="{{ $toursActive ? 'page' : 'false' }}">Tours</a>
                    <a href="{{ route('home') }}#about">About us</a>
                    <a href="#contact">Contact</a>
                </nav>

                <a class="travel-brand" href="{{ route('home') }}" aria-label="{{ $travelProfile->name }} home">
                    <img class="travel-brand__default" src="{{ $travelProfile->logoUrl }}" alt="{{ $travelProfile->name }}" width="199" height="70">
                    <img class="travel-brand__dark" src="{{ $travelProfile->lightLogoUrl }}" alt="" aria-hidden="true" width="199" height="70">
                </a>

                <nav class="travel-primary-nav__tools" aria-label="Account and appearance">
                    <button class="travel-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
                    <a class="travel-workspace-link" href="{{ $workspaceUrl }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i><span>{{ $workspaceLabel }}</span></a>
                    @guest
                        <a class="travel-button travel-button--compact" href="{{ route('register') }}">Create account</a>
                    @else
                        <a class="travel-button travel-button--compact" href="{{ route('travel-tours.storefront.catalog.index') }}">Find a tour</a>
                    @endguest
                </nav>
            </div>
        </div>
    </div>

    <div class="travel-header__tablet d-none d-md-grid d-xl-none">
        <button class="travel-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#travelMobileMenu" aria-controls="travelMobileMenu" aria-label="Open navigation" title="Menu"><i data-lucide="menu" aria-hidden="true"></i></button>
        <a class="travel-brand" href="{{ route('home') }}" aria-label="{{ $travelProfile->name }} home">
            <img class="travel-brand__default" src="{{ $travelProfile->logoUrl }}" alt="{{ $travelProfile->name }}" width="199" height="70">
            <img class="travel-brand__dark" src="{{ $travelProfile->lightLogoUrl }}" alt="" aria-hidden="true" width="199" height="70">
        </a>
        <div class="travel-header__compact-tools">
            <a class="travel-icon-button" href="{{ $workspaceUrl }}" aria-label="{{ $workspaceLabel }}" title="{{ $workspaceLabel }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i></a>
            @guest
                <a class="travel-button travel-button--compact" href="{{ route('register') }}">Sign up</a>
            @endguest
            <button class="travel-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="travel-header__mobile d-grid d-md-none">
        <button class="travel-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#travelMobileMenu" aria-controls="travelMobileMenu" aria-label="Open navigation" title="Menu"><i data-lucide="menu" aria-hidden="true"></i></button>
        <a class="travel-brand" href="{{ route('home') }}" aria-label="{{ $travelProfile->name }} home">
            <img class="travel-brand__default" src="{{ $travelProfile->logoUrl }}" alt="{{ $travelProfile->name }}" width="160" height="56">
            <img class="travel-brand__dark" src="{{ $travelProfile->lightLogoUrl }}" alt="" aria-hidden="true" width="160" height="56">
        </a>
        <a class="travel-icon-button" href="{{ $workspaceUrl }}" aria-label="{{ $workspaceLabel }}" title="{{ $workspaceLabel }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i></a>
    </div>
</header>

<aside class="offcanvas offcanvas-start travel-mobile-menu" tabindex="-1" id="travelMobileMenu" aria-labelledby="travelMobileMenuTitle">
    <div class="offcanvas-header">
        <a class="travel-brand" href="{{ route('home') }}" id="travelMobileMenuTitle">
            <img class="travel-brand__default" src="{{ $travelProfile->logoUrl }}" alt="{{ $travelProfile->name }}" width="180" height="64">
            <img class="travel-brand__dark" src="{{ $travelProfile->lightLogoUrl }}" alt="" aria-hidden="true" width="180" height="64">
        </a>
        <button class="travel-icon-button" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation" title="Close"><i data-lucide="x" aria-hidden="true"></i></button>
    </div>
    <div class="offcanvas-body">
        <nav class="travel-mobile-menu__links" aria-label="Mobile navigation">
            <a href="{{ route('home') }}"><span>Home</span><i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('travel-tours.storefront.catalog.index') }}"><span>Explore tours</span><i data-lucide="map" aria-hidden="true"></i></a>
            <a href="{{ route('home') }}#about"><span>About us</span><i data-lucide="building-2" aria-hidden="true"></i></a>
            <a href="#contact"><span>Contact</span><i data-lucide="phone" aria-hidden="true"></i></a>
        </nav>
        <div class="travel-mobile-menu__actions">
            <a class="travel-button travel-button--wide" href="{{ $workspaceUrl }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i>{{ $workspaceLabel }}</a>
            @guest
                <a class="travel-button travel-button--quiet travel-button--wide" href="{{ route('register') }}"><i data-lucide="user-plus" aria-hidden="true"></i>Create an account</a>
            @endguest
            <button class="travel-button travel-button--quiet travel-button--wide" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController"><i data-lucide="palette" aria-hidden="true"></i>Appearance</button>
        </div>
        <address class="travel-mobile-menu__contact">
            @if ($travelProfile->email)
                <a href="mailto:{{ $travelProfile->email }}">{{ $travelProfile->email }}</a>
            @endif
            @if ($travelProfile->phoneLink)
                <a href="tel:{{ $travelProfile->phoneLink }}">{{ $travelProfile->phone }}</a>
            @endif
            @if ($travelProfile->address)
                <span>{{ $travelProfile->address }}</span>
            @endif
        </address>
    </div>
</aside>
