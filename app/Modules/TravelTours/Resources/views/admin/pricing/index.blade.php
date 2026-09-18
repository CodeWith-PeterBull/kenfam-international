@extends('layouts.dashboard-layout')

@section('title', 'Tour Pricing')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Tour pricing</h4><h6>Rate plans, seasonal rules, and promotion codes</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.pricing.promotions') }}"><i class="ti ti-discount-2 me-2" aria-hidden="true"></i>Promotions</a>
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.index') }}"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour catalog</a>
            </div>
        </div>
        <section class="card aureon-panel aureon-table-panel" aria-labelledby="travel-pricing-tours-title">
            <div class="card-header">
                <h3 id="travel-pricing-tours-title" class="card-title mb-1">Tours</h3>
                <p class="aureon-muted mb-0">Choose a tour to manage its rate plans, fares, and pricing rules</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 travel-admin-table">
                    <thead>
                        <tr><th scope="col">Tour</th><th scope="col" class="text-center">Rate plans</th><th scope="col" class="text-center">Public plans</th><th scope="col" class="text-center">Departures</th><th scope="col" class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($tours as $tour)
                            <tr>
                                <td><strong class="d-block">{{ $tour->name }}</strong><small class="aureon-muted">{{ $tour->code }} &middot; {{ $tour->status->label() }}</small></td>
                                <td class="text-center">{{ $tour->rate_plans_count }}</td>
                                <td class="text-center">{{ $tour->public_rate_plans_count }}</td>
                                <td class="text-center">{{ $tour->departures_count }}</td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('travel-tours.admin.pricing.tour', $tour) }}">Manage pricing</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 aureon-muted">No tours yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tours->hasPages())
                <div class="card-footer">{{ $tours->links() }}</div>
            @endif
        </section>
    </main>
@endsection
