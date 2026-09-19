@php
    $profile = app(\App\Contracts\ResolvesInstitutionProfile::class)->current();
    $pageTitle = trim($__env->yieldContent('title', 'Stays'));
    $metaDescription = trim($__env->yieldContent('meta_description', 'Discover and reserve considered accommodation from '.$profile->name.'.'));
    $canonicalUrl = trim($__env->yieldContent('canonical', request()->url()));
    $socialImage = trim($__env->yieldContent('social_image', asset('aureon/assets/brand/twitter-card.png')));
    $brandLogo = $profile->mainLogoUrl ?: asset('aureon/assets/brand/logo.png');
    $brandLogoLight = $profile->hasCustomMainLogo ? $brandLogo : asset('aureon/assets/brand/logo-light.png');
    $brandIcon = $profile->logoIconUrl ?: asset('aureon/assets/brand/logo-icon.png');
    $navigationCategories = collect($storefrontNavigationCategories ?? []);
    $organizationSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        'name' => $profile->name,
        'url' => $profile->website ?: url('/'),
        'logo' => $brandLogo,
        'email' => $profile->primaryEmail,
        'telephone' => $profile->primaryPhone,
        'address' => $profile->address(),
    ], static fn ($value) => $value !== null && $value !== '');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="application-name" content="{{ $profile->shortName }} Stays">
    <meta property="og:site_name" content="{{ $profile->name }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageTitle }} | {{ $profile->shortName }} Stays">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="{{ $pageTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }} | {{ $profile->shortName }} Stays">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $pageTitle }} | {{ $profile->shortName }} Stays</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <link rel="icon" href="{{ $brandIcon }}" type="image/png">
    <script src="{{ asset('aureon/assets/js/theme-init.js') }}"></script>
    @include('layouts.partials.loader-bootstrap')
    <link href="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('aureon/assets/css/theme.css') }}" rel="stylesheet">
    @include('layouts.partials.brand-theme-tokens')
    @vite('app/Modules/PropertyBooking/Resources/css/storefront.css')
    <link href="{{ asset('aureon/assets/css/theme-controller.css') }}" rel="stylesheet">
    @livewireStyles
    @stack('head')
</head>
<body class="property-booking-storefront" data-nav="@yield('nav', 'stays')">
    <x-loader />
    <a class="pb-storefront-skip-link" href="#main-content">Skip to main content</a>
    @include('property-booking::storefront.partials.header')
    <main id="main-content">@yield('content')</main>
    @include('property-booking::storefront.partials.footer')
    @include('property-booking::storefront.partials.theme-controller')
    <button class="pb-storefront-back-top" type="button" data-pb-back-to-top aria-label="Back to top" title="Back to top"><i data-lucide="arrow-up" aria-hidden="true"></i></button>
    <script src="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/vendor/lucide/lucide.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/js/theme-controller.js') }}"></script>
    @livewireScripts
    @vite('app/Modules/PropertyBooking/Resources/js/storefront.js')
    @stack('scripts')
</body>
</html>
