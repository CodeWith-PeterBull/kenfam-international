@php
    $loaderMinimum = max(0, (int) config('aureon.dashboard.loader.minimum_visible_ms', 3000));
    $loaderSafety = max($loaderMinimum, (int) config('aureon.dashboard.loader.safety_timeout_ms', 8000));
    $loaderTransition = max(0, (int) config('aureon.dashboard.loader.transition_ms', 420));
@endphp
<style data-aureon-loader-critical>
    div#global-loader.aureon-loader {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: grid;
        place-items: center;
        color: var(--theme-text, #2f3037);
        background: var(--theme-background, #ffffff);
        opacity: 0;
        visibility: hidden;
        transition: opacity {{ $loaderTransition }}ms ease, visibility {{ $loaderTransition }}ms ease;
    }

    html.page-loader-enabled div#global-loader.aureon-loader:not([hidden]),
    html.page-loader-visible div#global-loader.aureon-loader {
        opacity: 1;
        visibility: visible;
    }

    html.page-loader-visible {
        overflow: hidden;
    }

    html.page-loader-leaving div#global-loader.aureon-loader {
        opacity: 0;
    }

    html[data-theme="dark"] div#global-loader.aureon-loader,
    html[data-bs-theme="dark"] div#global-loader.aureon-loader {
        color: var(--theme-text, #e8e8eb);
        background: var(--theme-background, #111217);
    }

    div#global-loader .aureon-loader__content {
        display: grid;
        width: min(280px, calc(100vw - 48px));
        justify-items: center;
        text-align: center;
    }

    div#global-loader .aureon-loader__content img {
        width: 62px;
        height: 62px;
        object-fit: contain;
    }

    div#global-loader .aureon-loader__title {
        margin: 16px 0 4px;
        color: var(--theme-heading, #17151d);
        font-family: var(--font-heading, Arial, sans-serif);
        font-size: var(--type-20, 1.25rem);
        font-weight: var(--font-weight-semibold, 600);
    }

    html[data-theme="dark"] div#global-loader .aureon-loader__title,
    html[data-bs-theme="dark"] div#global-loader .aureon-loader__title {
        color: var(--theme-heading, #ffffff);
    }

    div#global-loader .aureon-loader__bar {
        width: 164px;
        height: 2px;
        margin-top: 18px;
        overflow: hidden;
        background: var(--theme-border, #d9dbe1);
    }

    div#global-loader .aureon-loader__bar > span {
        display: block;
        width: 46%;
        height: 100%;
        background: var(--theme-primary, #6a753d);
        animation: aureon-critical-loader 900ms ease-in-out infinite;
    }

    div#global-loader .aureon-loader__label {
        margin-top: 10px;
        color: var(--theme-text-muted, #6b6d76);
        font-size: var(--type-11, .6875rem);
        font-weight: var(--font-weight-medium, 500);
        text-transform: uppercase;
    }

    @keyframes aureon-critical-loader {
        from { transform: translateX(-110%); }
        to { transform: translateX(235%); }
    }

    @media (prefers-reduced-motion: reduce) {
        div#global-loader.aureon-loader {
            transition-duration: 1ms;
        }

        div#global-loader .aureon-loader__bar > span {
            animation: none;
            transform: translateX(58%);
        }
    }
</style>
<script>
    ((root, doc, win) => {
        const minimumVisible = @json($loaderMinimum);
        const safetyTimeout = @json($loaderSafety);
        const transitionDuration = @json($loaderTransition);
        const revealedAt = Date.now();
        const reducedMotion = win.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        const frame = win.requestAnimationFrame || ((callback) => win.setTimeout(callback, 16));
        let dismissed = false;
        let holdTimer;
        let safetyTimer;

        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            win.clearTimeout(holdTimer);
            win.clearTimeout(safetyTimer);

            const visibleDuration = Date.now() - revealedAt;
            const loader = doc.querySelector('[data-page-loader]');
            root.classList.remove('page-loader-visible');
            root.classList.add('page-loader-leaving');
            root.dataset.pageLoaderState = 'ready';
            root.dataset.pageLoaderDuration = String(visibleDuration);

            win.setTimeout(() => {
                root.classList.remove('page-loader-enabled', 'page-loader-leaving');
                if (loader) {
                    loader.hidden = true;
                    loader.setAttribute('aria-hidden', 'true');
                }
                win.dispatchEvent(new CustomEvent('aureon:page-loader-dismissed', {
                    detail: { visibleDuration },
                }));
            }, reducedMotion ? 0 : transitionDuration);
        }

        function settle() {
            if (dismissed) return;
            const elapsed = Date.now() - revealedAt;
            const remaining = reducedMotion ? 0 : Math.max(0, minimumVisible - elapsed);
            holdTimer = win.setTimeout(dismiss, remaining);
        }

        function settleAfterPaint() {
            frame(() => frame(settle));
        }

        root.classList.add('page-loader-enabled', 'page-loader-visible');
        root.dataset.pageLoaderState = 'visible';
        safetyTimer = win.setTimeout(dismiss, safetyTimeout);

        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', settleAfterPaint, { once: true });
        } else {
            settleAfterPaint();
        }

        win.AureonPageLoader = {
            dismiss,
            minimumVisible,
            revealedAt,
            safetyTimeout,
        };
    })(document.documentElement, document, window);
</script>
