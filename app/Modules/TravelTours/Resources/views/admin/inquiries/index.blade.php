@extends('layouts.dashboard-layout')

@section('title', 'Tour Inquiries')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title">
                <h4>Tour inquiries</h4>
                <h6>Private, custom, and published-tour conversations and their follow-up</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.bookings.index') }}"><i class="ti ti-ticket me-2" aria-hidden="true"></i>Bookings</a>
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.index') }}"><i class="ti ti-map-route me-2" aria-hidden="true"></i>Tour catalog</a>
            </div>
        </div>
        <livewire:travel-tours.admin.inquiry-manager />
    </main>
@endsection
