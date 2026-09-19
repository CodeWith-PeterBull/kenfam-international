@php
    $travelProfile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current();
    $pageTitle = trim($__env->yieldContent('title', 'Tours and travel'));
    $metaDescription = trim($__env->yieldContent('meta_description', $travelProfile->metaDescription));
    $canonicalUrl = trim($__env->yieldContent('canonical', request()->url()));
    $socialImage = trim($__env->yieldContent('social_image', $travelProfile->socialImageUrl));
    $organizationSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => $travelProfile->name,
        'url' => url('/'),
        'logo' => $travelProfile->logoUrl,
        'email' => $travelProfile->email,
        'telephone' => $travelProfile->phone,
        'address' => $travelProfile->address ? [
            '@type' => 'PostalAddress',
            'streetAddress' => $travelProfile->address,
            'addressCountry' => config('travel-tours.defaults.country_code'),
        ] : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== '');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
    <meta name="application-name" content="{{ $travelProfile->shortName }} Travel">
    <meta name="theme-color" content="#70233a">
    <meta property="og:site_name" content="{{ $travelProfile->name }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageTitle }} | {{ $travelProfile->name }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="{{ $pageTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }} | {{ $travelProfile->name }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $pageTitle }} | {{ $travelProfile->name }}</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ $travelProfile->iconUrl }}" type="image/png">
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <script src="{{ asset('aureon/assets/js/theme-init.js') }}"></script>
    @include('layouts.partials.loader-bootstrap')
    <link href="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('aureon/assets/css/theme.css') }}" rel="stylesheet">
    @include('layouts.partials.brand-theme-tokens')
    <link href="{{ asset('aureon/assets/css/theme-controller.css') }}" rel="stylesheet">
    @vite('app/Modules/TravelTours/Resources/assets/css/storefront.css')
    @livewireStyles
    @stack('head')
</head>
<body class="travel-storefront" data-travel-page="@yield('page', 'general')">
    <x-loader context="travel experience" />
    <a class="travel-skip-link" href="#main-content">Skip to main content</a>
    @include('travel-tours::storefront.partials.header')
    <main id="main-content">@yield('content')</main>
    @include('travel-tours::storefront.partials.footer')
    @include('travel-tours::storefront.partials.theme-controller')
    <button class="travel-back-to-top" type="button" data-travel-back-to-top aria-label="Back to top" title="Back to top"><i data-lucide="arrow-up" aria-hidden="true"></i></button>
    <script src="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/vendor/lucide/lucide.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/js/theme-controller.js') }}"></script>
    @livewireScripts
    @vite('app/Modules/TravelTours/Resources/assets/js/storefront.js')
    @stack('scripts')
</body>
</html>
