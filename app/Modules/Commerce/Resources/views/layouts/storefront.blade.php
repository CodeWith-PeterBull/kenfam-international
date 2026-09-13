@php
    $profile = app(\App\Contracts\ResolvesInstitutionProfile::class)->current();
    $pageTitle = trim($__env->yieldContent('title', 'Store'));
    $metaDescription = trim($__env->yieldContent('meta_description', 'Explore a considered catalog of products from '.$profile->name.'.'));
    $canonicalUrl = trim($__env->yieldContent('canonical', request()->url()));
    $socialImage = trim($__env->yieldContent('social_image', asset('aureon/assets/brand/twitter-card.png')));
    $brandLogo = $profile->mainLogoUrl ?: asset('aureon/assets/brand/logo.png');
    $brandLogoLight = $profile->hasCustomMainLogo ? $brandLogo : asset('aureon/assets/brand/logo-light.png');
    $brandIcon = $profile->logoIconUrl ?: asset('aureon/assets/brand/logo-icon.png');
    $navigationCategories = collect($storefrontNavigationCategories ?? []);
    $storeContact = \App\Modules\Commerce\Support\InstitutionContact::from($profile);
    $twitterSite = $storeContact->xHandle();
    $organizationSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $profile->name,
        'url' => $profile->website ?: url('/'),
        'logo' => $brandLogo,
        'email' => $profile->primaryEmail,
        'telephone' => $profile->primaryPhone,
        'sameAs' => $storeContact->sameAs() ?: null,
    ], static fn ($value) => $value !== null && $value !== '');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="application-name" content="{{ $profile->shortName }} Store">
    <meta property="og:site_name" content="{{ $profile->name }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageTitle }} | {{ $profile->shortName }} Store">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="{{ $pageTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    @if ($twitterSite)<meta name="twitter:site" content="{{ $twitterSite }}">@endif
    <meta name="twitter:title" content="{{ $pageTitle }} | {{ $profile->shortName }} Store">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <meta name="twitter:image:alt" content="{{ $pageTitle }}">
    <title>{{ $pageTitle }} | {{ $profile->shortName }} Store</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <link rel="icon" href="{{ $brandIcon }}" type="image/png">
    <script src="{{ asset('aureon/assets/js/theme-init.js') }}"></script>
    @include('layouts.partials.loader-bootstrap')
    <link href="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('aureon/assets/css/theme.css') }}" rel="stylesheet">
    @vite('app/Modules/Commerce/Resources/css/storefront.css')
    <link href="{{ asset('aureon/assets/css/theme-controller.css') }}" rel="stylesheet">
    @livewireStyles
    @stack('head')
</head>
<body class="commerce-storefront" data-nav="@yield('nav', 'shop')">
    <x-loader />
    <a class="commerce-skip-link" href="#main-content">Skip to main content</a>

    @include('commerce::storefront.partials.header', [
        'profile' => $profile,
        'brandLogo' => $brandLogo,
        'brandLogoLight' => $brandLogoLight,
        'navigationCategories' => $navigationCategories,
    ])

    <main id="main-content">
        @yield('content')
    </main>

    @include('commerce::storefront.partials.footer', [
        'profile' => $profile,
        'brandLogo' => $brandLogo,
        'brandLogoLight' => $brandLogoLight,
        'navigationCategories' => $navigationCategories,
    ])
    @include('commerce::storefront.partials.theme-controller')

    <button class="commerce-back-to-top" type="button" data-commerce-back-to-top aria-label="Back to top" title="Back to top">
        <i data-lucide="arrow-up" aria-hidden="true"></i>
    </button>

    <script src="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/vendor/lucide/lucide.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/js/theme-controller.js') }}"></script>
    @livewireScripts
    @vite('app/Modules/Commerce/Resources/js/storefront.js')
    @stack('scripts')
</body>
</html>
