@props([
    'status',
    'title',
    'message',
    'illustration',
    'recoverSession' => false,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#70233a">
    <title>{{ $status }} | {{ $title }} | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('aureon/assets/brand/favicon.png') }}">
    @include('layouts.partials.theme-settings')
    <link rel="stylesheet" href="{{ asset('build/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('build/plugins/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('build/css/aureon-errors.css') }}">
</head>
<body class="error-page">
    <main class="aureon-error-shell">
        <header class="aureon-error-header">
            <a href="{{ url('/') }}" class="aureon-error-brand" aria-label="{{ config('app.name') }} home">
                <img src="{{ asset('aureon/assets/brand/logo.png') }}" class="brand-default" alt="{{ config('app.name') }}">
                <img src="{{ asset('aureon/assets/brand/logo-light.png') }}" class="brand-light" alt="{{ config('app.name') }}">
            </a>
            <span>Secure corporate workspace</span>
        </header>

        <div class="aureon-error-content">
            <figure class="aureon-error-visual">
                <img src="{{ asset('build/img/server-status/'.$illustration) }}" alt="{{ $status }} {{ $title }} illustration">
            </figure>

            <section class="aureon-error-copy" aria-labelledby="error-page-title">
                <span class="aureon-error-code">Error {{ $status }}</span>
                <h1 id="error-page-title">{{ $title }}</h1>
                <p>{{ $message }}</p>
                <div class="aureon-error-actions">
                    {{ $actions }}
                </div>
                @if ($recoverSession)
                    <p class="aureon-error-status" aria-live="polite" data-session-recovery-status></p>
                @endif
            </section>
        </div>

        <footer class="aureon-error-footer">
            <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
            <span>Corporate engine by Meta Software Developers</span>
        </footer>
    </main>

    @if ($recoverSession)
        <script>
            (() => {
                const button = document.querySelector('[data-session-recovery]');
                const status = document.querySelector('[data-session-recovery-status]');

                if (!button) return;

                button.addEventListener('click', async () => {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    status.textContent = 'Refreshing your secure session...';

                    try {
                        window.sessionStorage.clear();

                        const removablePrefixes = ['aureon:form:', 'livewire:form:', 'livewire:upload:'];
                        for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
                            const key = window.localStorage.key(index);
                            if (key && removablePrefixes.some((prefix) => key.startsWith(prefix))) {
                                window.localStorage.removeItem(key);
                            }
                        }

                        if ('caches' in window) {
                            const cacheNames = await window.caches.keys();
                            await Promise.all(cacheNames.map((cacheName) => window.caches.delete(cacheName)));
                        }
                    } catch (_) {
                        // Recovery must continue even when a storage API is unavailable.
                    }

                    const target = new URL(button.dataset.sessionRecovery, window.location.origin);
                    target.searchParams.set('_fresh', Date.now().toString());
                    window.location.replace(target.toString());
                });
            })();
        </script>
    @endif
</body>
</html>

