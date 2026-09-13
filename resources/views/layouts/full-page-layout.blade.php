{{--
    Full-page (auth / standalone) shell.

    Lean by design: auth screens load only Bootstrap, Tabler icons, the
    DreamPOS theme stylesheet (which owns the account-page/login-wrapper
    layout system), the Aureon token sheet, and a small vanilla auth script.
    No jQuery, feather, slimscroll, loader, or Livewire — none are used on
    these pages. (The CSK benchmark tried to slim these pages with
    Route::is() guards that matched nothing and shipped the full dashboard
    bundle; this shell uses explicit includes instead.)

    The theme-settings partial restores the persisted light/dark preference
    before first paint, so auth screens follow the dashboard appearance.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('layouts.partials.title-meta')
    @include('layouts.partials.theme-settings')
    <link rel="stylesheet" href="{{ asset('build/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('build/plugins/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('build/css/style.css') }}">
    @vite(['resources/css/aureon-dashboard.css', 'resources/css/aureon-auth.css'])
    @stack('styles')
</head>
<body class="account-page">
    <div class="main-wrapper">
        @yield('content')
    </div>

    <script src="{{ asset('build/js/bootstrap.bundle.min.js') }}"></script>
    @vite('resources/js/aureon-auth.js')
    @stack('scripts')
</body>
</html>
