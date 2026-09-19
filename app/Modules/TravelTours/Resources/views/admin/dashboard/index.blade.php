@extends('layouts.dashboard-layout')

@use('App\Modules\TravelTours\Support\MoneyFormatter')

@section('title', 'Travel Operations')

@section('content')
<main id="travel-dashboard">
    <div class="page-header">
        <div class="page-title"><h4>Travel operations</h4><h6>Tours, departures, bookings, payments, and inquiry follow-up at a glance</h6></div>
        <div class="page-btn"><a class="btn btn-outline-secondary" href="{{ route('travel-tours.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-world me-2"></i>Public tours</a></div>
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
                <article class="card aureon-stat mb-4 w-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti ti-{{ $stat['icon'] }}"></i></span>
                        <div><strong class="fs-3 d-block">{{ number_format($stat['value']) }}</strong><span>{{ $stat['label'] }}</span></div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <div class="row">
        <div class="col-xl-7">
            <section class="card aureon-panel mb-4">
                <div class="card-header"><h3 class="card-title mb-1">Upcoming departures</h3><p class="aureon-muted mb-0">Next scheduled journeys and capacity</p></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Tour</th><th>Starts</th><th>Capacity</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($departures as $departure)
                                <tr>
                                    <td><strong>{{ $departure->tour->name }}</strong><small class="d-block text-muted">{{ $departure->code }}</small></td>
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
            <section class="card aureon-panel mb-4">
                <div class="card-header"><h3 class="card-title mb-1">Inquiry queue</h3><p class="aureon-muted mb-0">Open conversations requiring follow-up</p></div>
                <div class="card-body">
                    @forelse ($inquiries as $inquiry)
                        <article class="d-flex justify-content-between gap-3 border-bottom py-3">
                            <div><strong>{{ $inquiry->contact_name }}</strong><small class="d-block text-muted">{{ Str::limit($inquiry->message, 70) }}</small></div>
                            <span class="badge text-bg-light h-100">{{ $inquiry->status->label() }}</span>
                        </article>
                    @empty
                        <p class="text-muted mb-0">No open inquiries.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <section class="card aureon-panel">
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
                            <td><strong>{{ $booking->booking_number }}</strong><small class="d-block text-muted">{{ $booking->tour_name_snapshot }}</small></td>
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
