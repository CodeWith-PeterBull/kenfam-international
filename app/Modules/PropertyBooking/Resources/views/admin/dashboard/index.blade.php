@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Operations')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    @php
        $permission = \App\Modules\PropertyBooking\Support\PropertyBookingPermission::class;
        $user = auth()->user();
        $formatMoney = static fn (int $minor, ?string $currency = null): string => \App\Modules\PropertyBooking\Support\MoneyFormatter::format($minor, $currency ?? $snapshot->currencyCode);
        $bookingUrl = $user->can($permission::VIEW_BOOKINGS) ? route('property-booking.admin.bookings.index') : null;
        $readinessUrl = $user->can($permission::MANAGE_READINESS) ? route('property-booking.admin.readiness.index') : null;
        $shiftUrl = $user->can($permission::MANAGE_SHIFTS) ? route('property-booking.pob.admin.shifts.index') : null;
        $guestUrl = $user->can($permission::MANAGE_GUESTS) ? route('property-booking.admin.guests.index') : null;
        $storefrontUrl = Route::has('property-booking.storefront.catalog.index') ? route('property-booking.storefront.catalog.index') : null;
        $metrics = [
            ['label' => 'Payments collected', 'value' => $formatMoney($snapshot->paymentsCollectedMinor), 'meta' => $snapshot->range->label(), 'icon' => 'ti-cash-banknote', 'color' => '#28656b', 'href' => $bookingUrl],
            ['label' => 'Booked value', 'value' => $formatMoney($snapshot->bookedValueMinor), 'meta' => 'Active and completed', 'icon' => 'ti-chart-bar', 'color' => '#70233a', 'href' => $bookingUrl],
            ['label' => 'Bookings received', 'value' => number_format($snapshot->bookingsReceived), 'meta' => $snapshot->range->label(), 'icon' => 'ti-calendar-plus', 'color' => '#8a6427', 'href' => $bookingUrl],
            ['label' => 'Average booking', 'value' => $formatMoney($snapshot->averageBookingValueMinor), 'meta' => $snapshot->currencyCode.' contributing bookings', 'icon' => 'ti-calculator', 'color' => '#4f5d75', 'href' => $bookingUrl],
            ['label' => 'Arrivals today', 'value' => number_format($snapshot->arrivalsToday), 'meta' => 'Property-local calendar', 'icon' => 'ti-door-enter', 'color' => '#2f7d64', 'href' => $bookingUrl ? route('property-booking.admin.bookings.index', ['booking-status' => 'confirmed', 'booking-stay' => 'expected']) : null],
            ['label' => 'Departures today', 'value' => number_format($snapshot->departuresToday), 'meta' => 'Property-local calendar', 'icon' => 'ti-door-exit', 'color' => '#2c6e93', 'href' => $bookingUrl ? route('property-booking.admin.bookings.index', ['booking-stay' => 'checked_in']) : null],
            ['label' => 'Guests in house', 'value' => number_format($snapshot->inHouseStays), 'meta' => $snapshot->activeBookings.' active bookings', 'icon' => 'ti-bed', 'color' => '#6e5a3b', 'href' => $bookingUrl ? route('property-booking.admin.bookings.index', ['booking-stay' => 'checked_in']) : null],
            ['label' => 'Unit occupancy', 'value' => $snapshot->occupancyPercentage().'%', 'meta' => $snapshot->occupiedUnits.' of '.$snapshot->activeUnits.' active units', 'icon' => 'ti-building-community', 'color' => '#7b4f78', 'href' => $readinessUrl],
        ];
        $chartPayload = [
            'currency' => $snapshot->currencyCode,
            'decimals' => (int) config('property-booking.defaults.currency_decimals', 2),
            'labels' => array_map(static fn ($point) => $point->label, $snapshot->bookingTrend),
            'web' => array_map(static fn ($point) => $point->webMinor, $snapshot->bookingTrend),
            'pob' => array_map(static fn ($point) => $point->pointOfBookingMinor, $snapshot->bookingTrend),
            'admin' => array_map(static fn ($point) => $point->adminMinor, $snapshot->bookingTrend),
        ];
    @endphp

    <main id="property-booking-dashboard" class="property-booking-admin pb-operations-dashboard" data-property-booking-admin data-property-booking-dashboard>
        <div class="page-header pb-dashboard-header">
            <div class="page-title"><h4>Accommodation operations</h4><h6>Property-scoped reservations, occupancy, readiness, settlement, and reception shifts</h6></div>
            <nav class="pb-dashboard-range" aria-label="Accommodation reporting period">
                @foreach($ranges as $range)
                    <a href="{{ route('property-booking.admin.dashboard', ['range' => $range->value]) }}" class="{{ $snapshot->range === $range ? 'is-active' : '' }}" @if($snapshot->range === $range) aria-current="page" @endif>{{ $range->label() }}</a>
                @endforeach
            </nav>
        </div>

        <section class="aureon-welcome pb-dashboard-welcome mb-4" aria-labelledby="accommodation-welcome-title">
            <div class="position-relative z-1 pb-dashboard-welcome__content"><div><p class="mb-2 text-uppercase fw-semibold small">Aureon accommodation administration</p><h2 id="accommodation-welcome-title" class="text-white mb-2">Stay operations in one focused workspace</h2><p>Monitor arrivals, departures, guest presence, unit readiness, collection, and active reception shifts across the properties assigned to you.</p></div><nav class="pb-dashboard-welcome__actions" aria-label="Accommodation quick actions">
                @if($bookingUrl)<a href="{{ $bookingUrl }}"><i class="ti ti-calendar-check" aria-hidden="true"></i>Bookings</a>@endif
                @if($guestUrl)<a href="{{ $guestUrl }}"><i class="ti ti-users-group" aria-hidden="true"></i>Guests</a>@endif
                @if($readinessUrl)<a href="{{ $readinessUrl }}"><i class="ti ti-brush" aria-hidden="true"></i>Readiness</a>@endif
                @can($permission::ACCESS_POB)<a href="{{ route('property-booking.pob.terminal') }}"><i class="ti ti-device-desktop" aria-hidden="true"></i>POB</a>@endcan
                @if($shiftUrl)<a href="{{ $shiftUrl }}"><i class="ti ti-clock-dollar" aria-hidden="true"></i>Shifts</a>@endif
                @if($storefrontUrl)<a href="{{ $storefrontUrl }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-world" aria-hidden="true"></i>Public stays</a>@endif
            </nav></div>
        </section>

        @if($snapshot->mixedCurrencyBookingsExcluded > 0)<div class="alert alert-info" role="status"><i class="ti ti-info-circle me-2" aria-hidden="true"></i>{{ number_format($snapshot->mixedCurrencyBookingsExcluded) }} non-{{ $snapshot->currencyCode }} {{ \Illuminate\Support\Str::plural('booking', $snapshot->mixedCurrencyBookingsExcluded) }} {{ $snapshot->mixedCurrencyBookingsExcluded === 1 ? 'was' : 'were' }} excluded from monetary totals.</div>@endif

        <section class="row pb-dashboard-metrics" aria-label="Accommodation statistics">
            @foreach($metrics as $metric)
                <div class="col-xxl-3 col-xl-4 col-sm-6 d-flex"><article class="card aureon-stat pb-dashboard-metric mb-4" data-property-booking-metric style="--stat-color: {{ $metric['color'] }}">
                    @if($metric['href'])<a href="{{ $metric['href'] }}" class="pb-dashboard-metric__link" aria-label="{{ $metric['label'] }}: {{ $metric['value'] }}">@else<div class="pb-dashboard-metric__link">@endif
                        <span class="aureon-stat__icon"><i class="ti {{ $metric['icon'] }}" aria-hidden="true"></i></span><span class="pb-dashboard-metric__copy"><strong>{{ $metric['value'] }}</strong><span>{{ $metric['label'] }}</span><small>{{ $metric['meta'] }}</small></span>@if($metric['href'])<i class="ti ti-arrow-up-right pb-dashboard-metric__arrow" aria-hidden="true"></i>@endif
                    @if($metric['href'])</a>@else</div>@endif
                </article></div>
            @endforeach
        </section>

        <div class="row">
            <div class="col-xl-8 d-flex"><section class="card aureon-panel pb-dashboard-panel mb-4" aria-labelledby="booking-trend-title"><div class="card-header d-flex align-items-start justify-content-between gap-3"><div><h3 id="booking-trend-title" class="card-title mb-1">Booked value trend</h3><p class="aureon-muted mb-0">Contributing bookings by intake channel and UTC placement day</p></div><span class="pb-badge pb-badge--neutral">{{ $snapshot->periodLabel() }}</span></div><div class="card-body"><div class="pb-dashboard-chart"><canvas data-property-booking-trend-chart role="img" aria-label="Web, point of booking, and administration booked value trend" aria-describedby="booking-trend-data-title"></canvas></div><details class="pb-dashboard-chart-data mt-3"><summary id="booking-trend-data-title">View daily booking data</summary><div class="table-responsive mt-3"><table class="table pb-table pb-table--compact mb-0"><thead><tr><th>Date</th><th class="text-end">Web</th><th class="text-end">POB</th><th class="text-end">Admin</th><th class="text-end">Total</th></tr></thead><tbody>@foreach($snapshot->bookingTrend as $point)<tr><th>{{ $point->date }}</th><td class="text-end">{{ $formatMoney($point->webMinor) }}</td><td class="text-end">{{ $formatMoney($point->pointOfBookingMinor) }}</td><td class="text-end">{{ $formatMoney($point->adminMinor) }}</td><td class="text-end fw-semibold">{{ $formatMoney($point->totalMinor()) }}</td></tr>@endforeach</tbody></table></div></details></div></section></div>
            <div class="col-xl-4 d-flex"><section class="card aureon-panel pb-dashboard-panel mb-4" aria-labelledby="booking-action-title"><div class="card-header"><h3 id="booking-action-title" class="card-title mb-1">Action queue</h3><p class="aureon-muted mb-0">{{ number_format($snapshot->actionCount()) }} current operational flags</p></div><div class="card-body pb-action-queue">@foreach($snapshot->actionQueue as $summary) @php $url = $bookingUrl ? route('property-booking.admin.bookings.index', $summary->filter) : null; @endphp <article><span class="pb-action-queue__icon pb-action-queue__icon--{{ $summary->tone }}"><i class="ti {{ $summary->icon }}" aria-hidden="true"></i></span><div><h4>{{ $summary->label }}</h4><p>{{ $summary->description }}</p></div>@if($url)<a href="{{ $url }}" class="pb-badge pb-badge--{{ $summary->tone }}" aria-label="Review {{ $summary->count }} {{ strtolower($summary->label) }} items">{{ number_format($summary->count) }}</a>@else<span class="pb-badge pb-badge--{{ $summary->tone }}">{{ number_format($summary->count) }}</span>@endif</article>@endforeach</div></section></div>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex"><section class="card aureon-panel aureon-table-panel pb-dashboard-panel mb-4" aria-labelledby="recent-booking-title"><div class="card-header d-flex align-items-center justify-content-between gap-3"><div><h3 id="recent-booking-title" class="card-title mb-1">Recent bookings</h3><p class="aureon-muted mb-0">Latest property-scoped numbered reservations</p></div>@if($bookingUrl)<a href="{{ $bookingUrl }}" class="btn btn-sm btn-outline-primary">All bookings<i class="ti ti-arrow-right ms-2" aria-hidden="true"></i></a>@endif</div><div class="table-responsive"><table class="table pb-table pb-table--dashboard mb-0"><thead><tr><th>Booking</th><th>Guest</th><th>Arrival</th><th>Channel</th><th>Total</th><th>State</th><th class="text-end">Action</th></tr></thead><tbody>@forelse($snapshot->recentBookings as $booking)<tr><td><strong>{{ $booking->bookingNumber }}</strong><small class="d-block aureon-muted">{{ $booking->propertyName }}</small></td><td>{{ $booking->guestName }}</td><td><time datetime="{{ $booking->startsAt->toIso8601String() }}">{{ $booking->startsAt->timezone($booking->propertyTimezone)->format('d M, H:i') }}</time></td><td><span class="pb-badge pb-badge--neutral">{{ $booking->channel->label() }}</span></td><td class="fw-semibold text-nowrap">{{ $formatMoney($booking->totalMinor, $booking->currency) }}</td><td><span class="pb-badge {{ $booking->status->isTerminal() ? 'pb-badge--neutral' : 'pb-badge--success' }}">{{ $booking->status->label() }}</span><small class="d-block aureon-muted mt-1">{{ $booking->stayStatus->label() }}</small></td><td class="text-end">@if($bookingUrl)<a href="{{ route('property-booking.admin.bookings.index', ['booking-q' => $booking->bookingNumber]) }}" class="btn btn-icon btn-sm btn-outline-secondary" aria-label="Review {{ $booking->bookingNumber }}"><i class="ti ti-arrow-up-right" aria-hidden="true"></i></a>@else<span class="aureon-muted">-</span>@endif</td></tr>@empty<tr><td colspan="7"><div class="pb-empty"><i class="ti ti-calendar-off" aria-hidden="true"></i><strong>No bookings have been placed.</strong></div></td></tr>@endforelse</tbody></table></div></section></div>
            <div class="col-xl-4 d-flex"><section class="card aureon-panel pb-dashboard-panel mb-4" aria-labelledby="readiness-summary-title"><div class="card-header d-flex align-items-center justify-content-between gap-3"><div><h3 id="readiness-summary-title" class="card-title mb-1">Unit readiness</h3><p class="aureon-muted mb-0">{{ number_format($snapshot->activeUnits) }} active concrete units</p></div>@if($readinessUrl)<a href="{{ $readinessUrl }}" class="btn btn-icon btn-sm btn-outline-secondary" aria-label="Open readiness board"><i class="ti ti-arrow-up-right"></i></a>@endif</div><div class="card-body pb-readiness-summary">@foreach(\App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::cases() as $status) @php $count = $snapshot->readinessCounts[$status->value] ?? 0; $percentage = $snapshot->activeUnits > 0 ? (int) round(($count / $snapshot->activeUnits) * 100) : 0; @endphp <div><div><span>{{ $status->label() }}</span><strong>{{ number_format($count) }}</strong></div><div class="progress" role="progressbar" aria-label="{{ $status->label() }} units" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $percentage }}%"></div></div></div>@endforeach</div></section></div>
        </div>

        <section class="card aureon-panel pb-dashboard-panel mb-0" aria-labelledby="active-shift-title"><div class="card-header d-flex align-items-center justify-content-between gap-3"><div><h3 id="active-shift-title" class="card-title mb-1">Active reception shifts</h3><p class="aureon-muted mb-0">{{ number_format($snapshot->openShiftCount) }} current receptionist-owned sessions</p></div>@if($shiftUrl)<a href="{{ $shiftUrl }}" class="btn btn-sm btn-outline-primary">Manage shifts<i class="ti ti-arrow-right ms-2"></i></a>@endif</div><div class="card-body pb-active-shifts">@forelse($snapshot->activeShifts as $shift)<article><span class="pb-icon-tile" style="--pb-stat-color: #28656b"><i class="ti ti-clock-dollar" aria-hidden="true"></i></span><div><h4>{{ $shift->registerName }}</h4><p>{{ $shift->propertyName }} &middot; {{ $shift->registerCode }}</p></div><div><strong>{{ $shift->receptionistName }}</strong><small>Opened {{ $shift->openedAt->diffForHumans() }}</small></div><div class="text-end"><strong>{{ $formatMoney($shift->expectedCashMinor, $shift->currency) }}</strong><small>Expected cash</small></div></article>@empty<div class="pb-empty"><i class="ti ti-clock-off" aria-hidden="true"></i><strong>No reception shift is currently open.</strong></div>@endforelse</div></section>

        <script id="property-booking-dashboard-chart-data" type="application/json">@json($chartPayload)</script>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('build/plugins/chartjs/chart.min.js') }}"></script>
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
