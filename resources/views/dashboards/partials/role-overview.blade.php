<div class="page-header">
    <div class="page-title">
        <h4>{{ $title }}</h4>
        <h6>{{ $summary }}</h6>
    </div>
    <div class="page-btn">
        <a href="{{ route($primaryAction['route']) }}" class="btn btn-primary"><i class="ti {{ $primaryAction['icon'] }} me-2"></i>{{ $primaryAction['label'] }}</a>
    </div>
</div>

<section class="aureon-welcome mb-4" aria-labelledby="role-dashboard-welcome-title">
    <div class="position-relative z-1">
        <p class="mb-2 text-uppercase fw-semibold small">{{ $eyebrow }}</p>
        <h2 id="role-dashboard-welcome-title" class="text-white mb-2">Welcome back, {{ auth()->user()->display_name }}</h2>
        <p>{{ $summary }}</p>
    </div>
</section>

<section class="row" aria-label="Workspace statistics">
    @foreach ($stats as $stat)
        <div class="col-xl-3 col-sm-6 d-flex">
            <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                    <div><h3>{{ number_format($stat['value']) }}</h3><p>{{ $stat['label'] }}</p></div>
                </div>
            </article>
        </div>
    @endforeach
</section>

<div class="row">
    <div class="col-xl-8 d-flex">
        <section class="card aureon-panel mb-4" aria-labelledby="workspace-priorities-title">
            <div class="card-header"><h3 id="workspace-priorities-title" class="card-title mb-0">Workspace priorities</h3></div>
            <div class="card-body">
                @foreach ($focusItems as $item)
                    <div class="aureon-list-row align-items-center">
                        <div><h6 class="mb-1">{{ $item['title'] }}</h6><p class="aureon-muted mb-0">{{ $item['meta'] }}</p></div>
                        <span class="badge aureon-role-badge text-nowrap">{{ $item['status'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    <div class="col-xl-4 d-flex">
        <section class="card aureon-panel mb-4" aria-labelledby="quick-links-title">
            <div class="card-header"><h3 id="quick-links-title" class="card-title mb-0">Quick links</h3></div>
            <div class="card-body aureon-dashboard-links">
                @foreach ($quickLinks as $link)
                    <a href="{{ route($link['route']) }}" class="aureon-dashboard-link"><i class="ti {{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span><i class="ti ti-chevron-right ms-auto"></i></a>
                @endforeach
                @if (config('commerce.enabled', true) && Route::has('commerce.storefront.catalog.index'))
                    <a href="{{ route('commerce.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer" class="aureon-dashboard-link"><i class="ti ti-building-store"></i><span>Shop frontend</span><i class="ti ti-external-link ms-auto"></i></a>
                @endif
            </div>
        </section>
    </div>
</div>

@if (config('commerce.enabled', true) && config('commerce.pos.sales_history.dashboard_enabled', true))
    @can(\App\Modules\Commerce\Support\CommercePermission::ACCESS_POS)
        <livewire:commerce.pos.cashier-sales-history surface="dashboard" key="role-dashboard-cashier-sales-history" />
    @endcan
@endif
