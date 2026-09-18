@extends('layouts.dashboard-layout')

@section('title', 'Travel Booking Desk')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Travel booking desk</h4><h6>Assisted tour selection, traveller capture, settlement, and receipts</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.bookings.index') }}"><i class="ti ti-list-details me-2" aria-hidden="true"></i>Bookings</a>
                @can('create', \App\Modules\TravelTours\PointOfBooking\Models\BookingRegister::class)
                    <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.shifts.index') }}"><i class="ti ti-cash-register me-2" aria-hidden="true"></i>Registers and shifts</a>
                @endcan
            </div>
        </div>
        <livewire:travel-tours.pob.terminal />
    </main>
@endsection
