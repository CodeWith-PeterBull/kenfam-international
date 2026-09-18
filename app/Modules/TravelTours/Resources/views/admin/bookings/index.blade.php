@extends('layouts.dashboard-layout')

@section('title', 'Tour Bookings')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Tour bookings</h4><h6>Reservations, payments, and lifecycle decisions</h6></div>
            <div class="page-btn"><a class="btn btn-outline-secondary" href="{{ route('travel-tours.pob.terminal') }}"><i class="ti ti-device-desktop me-2" aria-hidden="true"></i>Booking desk</a></div>
        </div>
        <livewire:travel-tours.admin.booking-manager />
    </main>
@endsection
