@extends('layouts.dashboard-layout')

@section('title', 'Commerce overview')

@push('styles')
    @vite([
        'app/Modules/Commerce/Resources/css/admin-dashboard.css',
        'app/Modules/Commerce/Resources/css/cashier-sales-history.css',
    ])
@endpush

@section('content')
    @php
        $permission = \App\Modules\Commerce\Support\CommercePermission::class;
        $formatMoney = static fn (int $minor): string => \App\Modules\Commerce\Support\MoneyFormatter::format($minor);
        $user = auth()->user();
        $orderUrl = $user->can($permission::VIEW_ORDERS) ? route('commerce.admin.orders.index') : null;
        $inventoryUrl = $user->can($permission::VIEW_INVENTORY) ? route('commerce.admin.inventory.index') : null;
        $tillUrl = $user->can($permission::MANAGE_TILLS) ? route('commerce.pos.admin.tills.index') : null;
        $customerUrl = $user->can($permission::MANAGE_CUSTOMERS) ? route('commerce.admin.customers.index') : null;
        $stockAlertsUrl = match (true) {
            ! $inventoryUrl => null,
            $snapshot->lowStockCount > 0 && $snapshot->outOfStockCount === 0 => route('commerce.admin.inventory.index', ['stock-state' => 'low']),
            $snapshot->lowStockCount === 0 && $snapshot->outOfStockCount > 0 => route('commerce.admin.inventory.index', ['stock-state' => 'out']),
            default => $inventoryUrl,
        };
        $metrics = [
            ['label' => 'Payments collected', 'value' => $formatMoney($snapshot->paymentsCollectedMinor), 'meta' => $snapshot->range->label(), 'icon' => 'ti-cash-banknote', 'color' => '#28656b', 'href' => $orderUrl],
            ['label' => 'Order value', 'value' => $formatMoney($snapshot->orderValueMinor), 'meta' => 'Excludes cancellations', 'icon' => 'ti-chart-bar', 'color' => '#70233a', 'href' => $orderUrl],
            ['label' => 'Orders received', 'value' => number_format($snapshot->ordersReceived), 'meta' => $snapshot->range->label(), 'icon' => 'ti-shopping-bag', 'color' => '#8a6427', 'href' => $orderUrl],
            ['label' => 'Average order value', 'value' => $formatMoney($snapshot->averageOrderValueMinor), 'meta' => 'Non-cancelled orders', 'icon' => 'ti-calculator', 'color' => '#4f5d75', 'href' => $orderUrl],
            ['label' => 'Orders requiring action', 'value' => number_format($snapshot->actionableOrders), 'meta' => 'Online fulfillment queue', 'icon' => 'ti-list-check', 'color' => '#b7791f', 'href' => $orderUrl ? route('commerce.admin.orders.index', ['order-channel' => 'web']) : null],
            ['label' => 'Stock alerts', 'value' => number_format($snapshot->lowStockCount + $snapshot->outOfStockCount), 'meta' => "{$snapshot->lowStockCount} low, {$snapshot->outOfStockCount} out", 'icon' => 'ti-alert-triangle', 'color' => '#b42318', 'href' => $stockAlertsUrl],
            ['label' => 'Open tills', 'value' => number_format($snapshot->activeTillCount), 'meta' => 'Current cashier sessions', 'icon' => 'ti-cash-register', 'color' => '#397a70', 'href' => $tillUrl],
            ['label' => 'Active customers', 'value' => number_format($snapshot->activeCustomerCount), 'meta' => 'Reusable customer records', 'icon' => 'ti-users', 'color' => '#6e5a3b', 'href' => $customerUrl],
        ];
        $chartPayload = [
            'currency' => $snapshot->currencyCode,
            'decimals' => (int) config('commerce.currency.decimal_places', 2),
            'labels' => array_map(static fn ($point) => $point->label, $snapshot->salesSeries),
            'web' => array_map(static fn ($point) => $point->webMinor, $snapshot->salesSeries),
            'pos' => array_map(static fn ($point) => $point->pointOfSaleMinor, $snapshot->salesSeries),
        ];
    @endphp

    <div id="commerce-dashboard" data-commerce-dashboard>
        <div class="page-header commerce-dashboard__header">
            <div class="page-title">
                <h4>Commerce overview</h4>
                <h6>Cross-channel sales and operations from {{ $snapshot->periodLabel() }}</h6>
            </div>
            <nav class="commerce-dashboard-range" aria-label="Commerce reporting period">
                @foreach ($ranges as $range)
                    <a href="{{ route('commerce.admin.dashboard', ['range' => $range->value]) }}"
                        class="{{ $snapshot->range === $range ? 'is-active' : '' }}"
                        @if ($snapshot->range === $range) aria-current="page" @endif>{{ $range->label() }}</a>
                @endforeach
            </nav>
        </div>

        <section class="aureon-welcome commerce-dashboard-welcome mb-4" aria-labelledby="commerce-welcome-title">
            <div class="position-relative z-1 commerce-dashboard-welcome__content">
                <div>
                    <p class="mb-2 text-uppercase fw-semibold small">Aureon commerce administration</p>
                    <h2 id="commerce-welcome-title" class="text-white mb-2">Sales, fulfillment, and stock in one view</h2>
                    <p>Track collected payments, order demand, inventory exceptions, and active cashier sessions without leaving the Commerce workspace.</p>
                </div>
                <div class="commerce-dashboard-welcome__actions" aria-label="Commerce quick actions">
                    <a href="{{ route('commerce.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-building-store" aria-hidden="true"></i>Shop frontend</a>
                    @can($permission::VIEW_ORDERS)
                        <a href="{{ route('commerce.admin.orders.index') }}"><i class="ti ti-receipt" aria-hidden="true"></i>Orders</a>
                    @endcan
                    @can($permission::VIEW_INVENTORY)
                        <a href="{{ route('commerce.admin.inventory.index') }}"><i class="ti ti-building-warehouse" aria-hidden="true"></i>Inventory</a>
                    @endcan
                    @can($permission::VIEW_PRODUCTS)
                        <a href="{{ route('commerce.admin.catalog.index') }}"><i class="ti ti-package" aria-hidden="true"></i>Catalog</a>
                    @endcan
                    @can($permission::ACCESS_POS)
                        <a href="{{ route('commerce.pos.terminal') }}"><i class="ti ti-device-desktop-dollar" aria-hidden="true"></i>POS</a>
                    @endcan
                    @can($permission::MANAGE_TILLS)
                        <a href="{{ route('commerce.pos.admin.tills.index') }}"><i class="ti ti-cash-register" aria-hidden="true"></i>Tills</a>
                    @endcan
                    @can($permission::MANAGE_DEMO_DATA)
                        <a href="{{ route('commerce.admin.demo-data.index') }}"><i class="ti ti-database-import" aria-hidden="true"></i>Demo data</a>
                    @endcan
                </div>
            </div>
        </section>

        <section class="row commerce-dashboard-metrics" aria-label="Commerce statistics">
            @foreach ($metrics as $metric)
                <div class="col-xxl-3 col-xl-4 col-sm-6 d-flex">
                    <article class="card aureon-stat commerce-dashboard-metric mb-4" data-commerce-metric style="--stat-color: {{ $metric['color'] }}">
                        @if ($metric['href'])
                            <a href="{{ $metric['href'] }}" class="commerce-dashboard-metric__link" aria-label="{{ $metric['label'] }}: {{ $metric['value'] }}">
                        @else
                            <div class="commerce-dashboard-metric__link">
                        @endif
                            <span class="aureon-stat__icon"><i class="ti {{ $metric['icon'] }}" aria-hidden="true"></i></span>
                            <span class="commerce-dashboard-metric__copy">
                                <strong>{{ $metric['value'] }}</strong>
                                <span>{{ $metric['label'] }}</span>
                                <small>{{ $metric['meta'] }}</small>
                            </span>
                            @if ($metric['href'])
                                <i class="ti ti-arrow-up-right commerce-dashboard-metric__arrow" aria-hidden="true"></i>
                            @endif
                        @if ($metric['href'])
                            </a>
                        @else
                            </div>
                        @endif
                    </article>
                </div>
            @endforeach
        </section>

        <div class="row">
            <div class="col-xl-8 d-flex">
                <section class="card aureon-panel commerce-dashboard-panel mb-4" aria-labelledby="sales-trend-title">
                    <div class="card-header d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <h3 id="sales-trend-title" class="card-title mb-1">Collected sales trend</h3>
                            <p class="aureon-muted mb-0">Completed payments grouped by order channel</p>
                        </div>
                        <span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">{{ $snapshot->range->label() }}</span>
                    </div>
                    <div class="card-body">
                        <div class="commerce-sales-chart" data-commerce-chart-container>
                            <canvas data-commerce-sales-chart role="img" aria-label="Collected online and point-of-sale payment trend" aria-describedby="commerce-sales-data-title"></canvas>
                        </div>
                        <details class="commerce-chart-data mt-3">
                            <summary id="commerce-sales-data-title">View daily sales data</summary>
                            <div class="table-responsive mt-3">
                                <table class="table align-middle mb-0">
                                    <thead><tr><th scope="col">Date</th><th scope="col" class="text-end">Online store</th><th scope="col" class="text-end">Point of sale</th><th scope="col" class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        @foreach ($snapshot->salesSeries as $point)
                                            <tr><th scope="row">{{ $point->date }}</th><td class="text-end">{{ $formatMoney($point->webMinor) }}</td><td class="text-end">{{ $formatMoney($point->pointOfSaleMinor) }}</td><td class="text-end fw-semibold">{{ $formatMoney($point->totalMinor()) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </div>
                </section>
            </div>

            <div class="col-xl-4 d-flex">
                <section class="card aureon-panel commerce-dashboard-panel mb-4" aria-labelledby="action-queue-title">
                    <div class="card-header">
                        <h3 id="action-queue-title" class="card-title mb-1">Online order queue</h3>
                        <p class="aureon-muted mb-0">Current orders awaiting fulfillment action</p>
                    </div>
                    <div class="card-body">
                        @foreach ($snapshot->actionQueue as $summary)
                            @php($statusUrl = $orderUrl ? route('commerce.admin.orders.index', ['order-channel' => 'web', 'order-status' => $summary->status->value]) : null)
                            <div class="aureon-list-row commerce-queue-row">
                                <div><span class="aureon-status-dot"></span><span>{{ $summary->status->label() }}</span></div>
                                @if ($statusUrl)
                                    <a href="{{ $statusUrl }}" class="badge aureon-commerce-badge aureon-commerce-badge--{{ $summary->status->value }}" aria-label="View {{ $summary->orderCount }} {{ strtolower($summary->status->label()) }} orders">{{ number_format($summary->orderCount) }}</a>
                                @else
                                    <span class="badge aureon-commerce-badge aureon-commerce-badge--{{ $summary->status->value }}">{{ number_format($summary->orderCount) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex">
                <section class="card aureon-panel aureon-table-panel commerce-dashboard-panel mb-4" aria-labelledby="recent-orders-title">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h3 id="recent-orders-title" class="card-title mb-1">Recent orders</h3>
                            <p class="aureon-muted mb-0">Latest numbered web and POS orders in this period</p>
                        </div>
                        @if ($orderUrl)<a href="{{ $orderUrl }}" class="btn btn-sm btn-outline-primary">All orders<i class="ti ti-arrow-right ms-2" aria-hidden="true"></i></a>@endif
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle commerce-dashboard-table mb-0">
                            <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Channel</th><th scope="col">Total</th><th scope="col">Payment</th><th scope="col">Fulfillment</th><th scope="col">Placed</th><th scope="col" class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse ($snapshot->recentOrders as $order)
                                    <tr>
                                        <td><strong>{{ $order->orderNumber }}</strong></td>
                                        <td>{{ $order->customerName }}</td>
                                        <td><span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">{{ $order->channel->label() }}</span></td>
                                        <td class="fw-semibold text-nowrap">{{ $formatMoney($order->totalMinor) }}</td>
                                        <td><span class="badge aureon-commerce-badge aureon-commerce-badge--payment-{{ $order->paymentStatus->value }}">{{ $order->paymentStatus->label() }}</span></td>
                                        <td><span class="badge aureon-commerce-badge aureon-commerce-badge--{{ $order->status->value }}">{{ $order->status->label() }}</span></td>
                                        <td><time datetime="{{ $order->placedAt->toIso8601String() }}">{{ $order->placedAt->format('d M, H:i') }}</time></td>
                                        <td class="text-end">
                                            @if ($orderUrl)
                                                <a href="{{ route('commerce.admin.orders.index', ['order-q' => $order->orderNumber]) }}" class="btn btn-sm btn-outline-primary" aria-label="Review order {{ $order->orderNumber }}"><i class="ti ti-arrow-up-right" aria-hidden="true"></i></a>
                                            @else
                                                <span class="aureon-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8"><div class="commerce-dashboard-empty"><i class="ti ti-shopping-bag" aria-hidden="true"></i><span>No orders were placed in this period.</span></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-xl-4 d-flex">
                <section class="card aureon-panel commerce-dashboard-panel mb-4" aria-labelledby="stock-alerts-title">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h3 id="stock-alerts-title" class="card-title mb-1">Stock exceptions</h3>
                            <p class="aureon-muted mb-0">Tracked products requiring attention</p>
                        </div>
                        @if ($stockAlertsUrl)<a href="{{ $stockAlertsUrl }}" class="btn btn-sm btn-outline-primary" aria-label="View stock exceptions"><i class="ti ti-arrow-up-right" aria-hidden="true"></i></a>@endif
                    </div>
                    <div class="card-body">
                        @forelse ($snapshot->stockAlerts as $stock)
                            <div class="aureon-list-row commerce-stock-row">
                                <div class="min-w-0">
                                    <h4>{{ $stock->productName }}</h4>
                                    <p>{{ $stock->sku }} · threshold {{ number_format($stock->lowStockThreshold) }}</p>
                                </div>
                                <div class="text-end">
                                    <span class="badge aureon-stock-badge aureon-stock-badge--{{ $stock->state }}">{{ $stock->state === 'out' ? 'Out' : 'Low' }}</span>
                                    <strong>{{ number_format($stock->onHand) }}</strong>
                                </div>
                            </div>
                        @empty
                            <div class="commerce-dashboard-empty"><i class="ti ti-circle-check" aria-hidden="true"></i><span>Tracked stock is healthy.</span></div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 d-flex">
                <section class="card aureon-panel commerce-dashboard-panel mb-4" aria-labelledby="sales-mix-title">
                    <div class="card-header">
                        <h3 id="sales-mix-title" class="card-title mb-1">Sales mix</h3>
                        <p class="aureon-muted mb-0">Order intake and completed tender distribution</p>
                    </div>
                    <div class="card-body commerce-sales-mix">
                        <div>
                            <h4>Order channels</h4>
                            @foreach ($snapshot->channelSummaries as $channel)
                                <div class="aureon-list-row">
                                    <span>{{ $channel->channel->label() }}<small>{{ number_format($channel->orderCount) }} orders</small></span>
                                    <strong>{{ $formatMoney($channel->orderValueMinor) }}</strong>
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <h4>Payment methods</h4>
                            @forelse ($snapshot->paymentMethodSummaries as $payment)
                                @php($share = $snapshot->paymentsCollectedMinor > 0 ? min(100, (int) round(($payment->totalMinor / $snapshot->paymentsCollectedMinor) * 100)) : 0)
                                <div class="commerce-payment-row">
                                    <div><span>{{ $payment->method->label() }}</span><strong>{{ $formatMoney($payment->totalMinor) }}</strong></div>
                                    <div class="progress aureon-progress" role="progressbar" aria-label="{{ $payment->method->label() }} share" aria-valuenow="{{ $share }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $share }}%"></div></div>
                                </div>
                            @empty
                                <div class="commerce-dashboard-empty commerce-dashboard-empty--compact"><span>No completed payments in this period.</span></div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-6 d-flex">
                <section class="card aureon-panel commerce-dashboard-panel mb-4" aria-labelledby="active-tills-title">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h3 id="active-tills-title" class="card-title mb-1">Active tills</h3>
                            <p class="aureon-muted mb-0">Current register ownership and expected cash</p>
                        </div>
                        @if ($tillUrl)<a href="{{ $tillUrl }}" class="btn btn-sm btn-outline-primary">Manage tills<i class="ti ti-arrow-right ms-2" aria-hidden="true"></i></a>@endif
                    </div>
                    <div class="card-body">
                        @forelse ($snapshot->activeTills as $till)
                            <div class="aureon-list-row commerce-till-row">
                                <div>
                                    <h4>{{ $till->registerName }}</h4>
                                    <p>{{ $till->registerCode }} · {{ $till->cashierName }}</p>
                                </div>
                                <div class="text-end">
                                    <strong>{{ $formatMoney($till->expectedCashMinor) }}</strong>
                                    <time datetime="{{ $till->openedAt->toIso8601String() }}">Open {{ $till->openedAt->diffForHumans() }}</time>
                                </div>
                            </div>
                        @empty
                            <div class="commerce-dashboard-empty"><i class="ti ti-lock" aria-hidden="true"></i><span>No cashier till is currently open.</span></div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        @if (config('commerce.pos.sales_history.dashboard_enabled', true))
            @can($permission::ACCESS_POS)
                <livewire:commerce.pos.cashier-sales-history surface="dashboard" key="commerce-dashboard-cashier-sales-history" />
            @endcan
        @endif
    </div>

    <script id="commerce-sales-chart-data" type="application/json">@json($chartPayload)</script>
@endsection

@push('scripts')
    <script src="{{ asset('build/plugins/chartjs/chart.min.js') }}"></script>
    @vite('app/Modules/Commerce/Resources/js/admin-dashboard.js')
@endpush
