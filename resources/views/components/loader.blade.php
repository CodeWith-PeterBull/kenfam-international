<div
    id="global-loader"
    class="aureon-loader"
    data-page-loader
    role="status"
    aria-live="polite"
    aria-label="Loading {{ config('app.name') }}"
>
    <div class="aureon-loader__content">
        <img
            src="{{ asset('aureon/assets/brand/logo-icon.png') }}"
            width="256"
            height="256"
            alt=""
            aria-hidden="true"
        >
        <p class="aureon-loader__title">{{ config('app.name') }}</p>
        <span class="aureon-loader__bar" aria-hidden="true"><span></span></span>
        <span class="aureon-loader__label" aria-hidden="true">Loading</span>
        <span class="visually-hidden">Loading dashboard</span>
    </div>
</div>
