<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('layouts.partials.title-meta')
    @include('layouts.partials.theme-settings')
    @include('layouts.partials.loader-bootstrap')
    @include('layouts.partials.head-css')
    @livewireStyles
    @stack('styles')
</head>
<body>
    <x-loader />

    @php($dashboardShell = \App\Support\DashboardRegistry::for(auth()->user()?->user_type))
    <div class="main-wrapper">
        @include($dashboardShell['topbar'])
        @include($dashboardShell['sidebar'])

        <div class="page-wrapper aureon-dashboard">
            <main class="content">
                @yield('content')
            </main>

            @include('layouts.partials.footer')
        </div>
    </div>

    @include('layouts.partials.dashboard-settings')
    @include('layouts.partials.vendor-scripts')
    @livewireScripts
    @stack('scripts')
</body>
</html>
