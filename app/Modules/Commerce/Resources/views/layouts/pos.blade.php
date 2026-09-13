@php
    $profile = app(\App\Contracts\ResolvesInstitutionProfile::class)->current();
    $brandLogo = $profile->mainLogoUrl ?: asset('aureon/assets/brand/logo.png');
    $brandLogoLight = $profile->hasCustomMainLogo ? $brandLogo : asset('aureon/assets/brand/logo-light.png');
    $operatorLabel = \App\Modules\Commerce\Support\CommerceRole::operatorLabel(auth()->user());
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('layouts.partials.title-meta')
    @include('layouts.partials.theme-settings')
    @include('layouts.partials.loader-bootstrap')
    @include('layouts.partials.head-css')
    @vite([
        'app/Modules/Commerce/Resources/css/pos.css',
        'app/Modules/Commerce/Resources/css/cashier-sales-history.css',
    ])
    @livewireStyles
    @stack('styles')
</head>
<body class="commerce-pos-shell">
    <x-loader />
    <a class="pos-skip-link" href="#pos-main">Skip to terminal</a>

    <header class="pos-topbar">
        <a class="pos-brand" href="{{ route('commerce.pos.terminal') }}" aria-label="{{ $profile->shortName }} point of sale">
            <img class="pos-brand__light" src="{{ $brandLogo }}" alt="{{ $profile->shortName }}">
            <img class="pos-brand__dark" src="{{ $brandLogoLight }}" alt="{{ $profile->shortName }}">
            <span><strong>Point of sale</strong><small>Transaction workspace</small></span>
        </a>
        <nav class="pos-topbar__actions" aria-label="Terminal tools">
            <a class="pos-icon-button" href="{{ route('commerce.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer" aria-label="Open shop frontend in a new tab" title="Shop frontend"><i class="ti ti-building-store"></i></a>
            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS)
                <a class="pos-icon-button" href="{{ route('commerce.pos.admin.registers.index') }}" aria-label="Manage registers" title="Registers"><i class="ti ti-settings"></i></a>
                <a class="pos-icon-button" href="{{ route('commerce.pos.admin.tills.index') }}" aria-label="Manage till sessions" title="Till sessions"><i class="ti ti-cash-register"></i></a>
            @endcan
            <a class="pos-icon-button" href="{{ route(\App\Support\DashboardRegistry::routeName(auth()->user()?->user_type)) }}" aria-label="Open dashboard" title="Dashboard"><i class="ti ti-layout-dashboard"></i></a>
            <button class="pos-icon-button" type="button" data-dashboard-theme-toggle aria-label="Toggle display theme" aria-pressed="false" title="Use dark theme"><i class="ti ti-moon"></i></button>
            <div class="pos-user"><span>{{ auth()->user()->initials }}</span><div><strong>{{ auth()->user()->display_name }}</strong><small>{{ $operatorLabel }}</small></div></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="pos-icon-button" type="submit" aria-label="Sign out" title="Sign out"><i class="ti ti-logout"></i></button>
            </form>
        </nav>
    </header>

    <main id="pos-main" class="pos-main">
        @yield('content')
    </main>

    @include('layouts.partials.vendor-scripts')
    @livewireScripts
    @vite('app/Modules/Commerce/Resources/js/pos.js')
    @stack('scripts')
</body>
</html>
