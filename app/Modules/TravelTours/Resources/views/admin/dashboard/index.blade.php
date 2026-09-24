@extends('layouts.dashboard-layout')

@use('App\Modules\TravelTours\Support\MoneyFormatter')
@use('App\Modules\TravelTours\Support\TravelToursPermission')

@section('title', 'Travel Operations')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
<main id="travel-dashboard" class="travel-admin travel-dashboard">
    <div class="page-header">
        <div class="page-title"><h4>Travel operations</h4><h6>Tours, departures, bookings, payments, and inquiry follow-up at a glance</h6></div>
        <div class="page-btn d-flex flex-wrap gap-2">
            @can(TravelToursPermission::VIEW_CATALOG)
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.index') }}"><i class="ti ti-map-route me-2" aria-hidden="true"></i>Catalog</a>
            @endcan
            @can(TravelToursPermission::VIEW_PRICING)
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.pricing.index') }}"><i class="ti ti-coins me-2" aria-hidden="true"></i>Pricing</a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('travel-tours.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-world me-2" aria-hidden="true"></i>Public tours</a>
        </div>
    </div>

    <section class="aureon-welcome mb-4">
        <div class="position-relative z-1">
            <p class="mb-2 text-uppercase fw-semibold small">Travel administration</p>
            <h2 class="text-white mb-2">Journeys and booking activity in one workspace</h2>
            <p>Coordinate published experiences, dated departures, traveler bookings, and sales inquiries without losing operational context.</p>
        </div>
    </section>

    <section class="row" aria-label="Travel statistics">
        @foreach ($stats as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                @can($stat['permission'])
                    <a class="card aureon-stat travel-dashboard-stat mb-4 w-100" href="{{ route($stat['route']) }}" aria-label="View {{ strtolower($stat['label']) }}">
                @else
                    <article class="card aureon-stat travel-dashboard-stat mb-4 w-100">
                @endcan
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti ti-{{ $stat['icon'] }}" aria-hidden="true"></i></span>
                        <div><strong class="fs-3 d-block">{{ number_format($stat['value']) }}</strong><span>{{ $stat['label'] }}</span></div>
                        @can($stat['permission'])
                            <i class="ti ti-arrow-up-right travel-dashboard-stat__arrow" aria-hidden="true"></i>
                        @endcan
                    </div>
                @can($stat['permission'])
                    </a>
                @else
                    </article>
                @endcan
            </div>
        @endforeach
    </section>

    <div class="row">
        <div class="col-xl-7">
            <section class="card aureon-panel travel-dashboard-panel mb-4">
                <div class="card-header d-flex align-items-start justify-content-between gap-3">
                    <div><h3 class="card-title mb-1">Upcoming departures</h3><p class="aureon-muted mb-0">Next scheduled journeys and capacity</p></div>
                    @can(TravelToursPermission::VIEW_DEPARTURES)
                        <a href="{{ route('travel-tours.admin.departures.index') }}" class="btn btn-sm btn-outline-primary">All departures</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Tour</th><th>Starts</th><th>Capacity</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($departures as $departure)
                                <tr>
                                    <td>
                                        @can(TravelToursPermission::VIEW_DEPARTURES)
                                            <a class="travel-dashboard-record" href="{{ route('travel-tours.admin.departures.index', ['search' => $departure->code]) }}"><strong>{{ $departure->tour->name }}</strong><small class="d-block text-muted">{{ $departure->code }}</small></a>
                                        @else
                                            <strong>{{ $departure->tour->name }}</strong><small class="d-block text-muted">{{ $departure->code }}</small>
                                        @endcan
                                    </td>
                                    <td>{{ $departure->starts_at->timezone($departure->timezone)->format('d M Y, H:i') }}</td>
                                    <td>{{ $departure->reserved_seats }} / {{ $departure->capacity }}</td>
                                    <td><span class="badge text-bg-light">{{ $departure->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4">No upcoming departures.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card aureon-panel travel-dashboard-panel mb-4">
                <div class="card-header d-flex align-items-start justify-content-between gap-3">
                    <div><h3 class="card-title mb-1">Inquiry queue</h3><p class="aureon-muted mb-0">Open conversations requiring follow-up</p></div>
                    @can(TravelToursPermission::VIEW_INQUIRIES)
                        <a href="{{ route('travel-tours.admin.inquiries.index') }}" class="btn btn-sm btn-outline-primary">All inquiries</a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($inquiries as $inquiry)
                        <article class="d-flex justify-content-between gap-3 border-bottom py-3">
                            <div>
                                @can(TravelToursPermission::VIEW_INQUIRIES)
                                    <a class="travel-dashboard-record" href="{{ route('travel-tours.admin.inquiries.index', ['search' => $inquiry->reference]) }}"><strong>{{ $inquiry->contact_name }}</strong><small class="d-block text-muted">{{ Str::limit($inquiry->message, 70) }}</small></a>
                                @else
                                    <strong>{{ $inquiry->contact_name }}</strong><small class="d-block text-muted">{{ Str::limit($inquiry->message, 70) }}</small>
                                @endcan
                            </div>
                            <span class="badge text-bg-light h-100">{{ $inquiry->status->label() }}</span>
                        </article>
                    @empty
                        <p class="text-muted mb-0">No open inquiries.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <section class="card aureon-panel travel-dashboard-panel">
        <div class="card-header d-flex justify-content-between">
            <div><h3 class="card-title mb-1">Recent bookings</h3><p class="aureon-muted mb-0">Latest reservations from every channel</p></div>
            <a href="{{ route('travel-tours.admin.bookings.index') }}" class="btn btn-sm btn-outline-primary">All bookings</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Booking</th><th>Customer</th><th>Placed</th><th>Total</th><th>State</th></tr></thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td>
                                @can(TravelToursPermission::VIEW_BOOKINGS)
                                    <a class="travel-dashboard-record" href="{{ route('travel-tours.admin.bookings.index', ['search' => $booking->booking_number]) }}"><strong>{{ $booking->booking_number }}</strong><small class="d-block text-muted">{{ $booking->tour_name_snapshot }}</small></a>
                                @else
                                    <strong>{{ $booking->booking_number }}</strong><small class="d-block text-muted">{{ $booking->tour_name_snapshot }}</small>
                                @endcan
                            </td>
                            <td>{{ $booking->customer->full_name }}</td>
                            <td>{{ $booking->placed_at->format('d M Y, H:i') }}</td>
                            <td>{{ MoneyFormatter::format($booking->total_minor, $booking->currency, $booking->currency_exponent) }}</td>
                            <td><span class="badge text-bg-light">{{ $booking->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4">No bookings have been placed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
