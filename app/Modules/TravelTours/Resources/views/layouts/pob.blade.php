@php
    $profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current();
    $operatorLabel = \App\Modules\TravelTours\Support\TravelToursRole::operatorLabel(auth()->user());
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('layouts.partials.title-meta')
    @include('layouts.partials.theme-settings')
    @include('layouts.partials.loader-bootstrap')
    @include('layouts.partials.head-css')
    @vite('app/Modules/TravelTours/Resources/assets/css/pob.css')
    @livewireStyles
    @stack('styles')
</head>
<body class="travel-tours-pob-shell">
    <x-loader />
    <a class="pob-skip-link" href="#pob-main">Skip to booking terminal</a>

    <header class="pob-topbar">
        <a class="pob-brand" href="{{ route('travel-tours.pob.terminal') }}" aria-label="{{ $profile->shortName }} booking desk">
            <img class="pob-brand__light" src="{{ $profile->logoUrl }}" alt="{{ $profile->shortName }}">
            <img class="pob-brand__dark" src="{{ $profile->lightLogoUrl }}" alt="{{ $profile->shortName }}">
            <span><strong>Point of Booking</strong><small>Travel desk workspace</small></span>
        </a>
        <nav class="pob-topbar__actions" aria-label="Terminal tools">
            @can(\App\Modules\TravelTours\Support\TravelToursPermission::MANAGE_SHIFTS)
                <a class="pob-icon-button" href="{{ route('travel-tours.pob.admin.registers.index') }}" aria-label="Manage booking registers" title="Registers"><i class="ti ti-cash-register" aria-hidden="true"></i></a>
                <a class="pob-icon-button" href="{{ route('travel-tours.pob.admin.shifts.index') }}" aria-label="Manage booking shifts" title="Shifts"><i class="ti ti-clock-dollar" aria-hidden="true"></i></a>
            @endcan
            <a class="pob-icon-button" href="{{ route(\App\Support\DashboardRegistry::routeName(auth()->user()?->user_type)) }}" aria-label="Open dashboard" title="Dashboard"><i class="ti ti-layout-dashboard" aria-hidden="true"></i></a>
            <button class="pob-icon-button" type="button" data-dashboard-theme-toggle aria-label="Toggle display theme" aria-pressed="false" title="Use dark theme"><i class="ti ti-moon" aria-hidden="true"></i></button>
            <div class="pob-user"><span aria-hidden="true">{{ auth()->user()->initials }}</span><div><strong>{{ auth()->user()->display_name }}</strong><small>{{ $operatorLabel }}</small></div></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="pob-icon-button" type="submit" aria-label="Sign out" title="Sign out"><i class="ti ti-logout" aria-hidden="true"></i></button>
            </form>
        </nav>
    </header>

    <main id="pob-main" class="pob-main">@yield('content')</main>

    @include('layouts.partials.vendor-scripts')
    @livewireScripts
    @vite('app/Modules/TravelTours/Resources/assets/js/pob.js')
    @stack('scripts')
</body>
</html>
