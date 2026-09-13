@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Bookings')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-bookings" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Accommodation bookings</h4>
                <h6>Reservation lifecycle, guest arrival, settlement, and unit assignment</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_READINESS)
                    <a href="{{ route('property-booking.admin.readiness.index') }}" class="btn btn-outline-secondary"><i class="ti ti-brush me-2"></i>Readiness</a>
                @endcan
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::ACCESS_POB)
                    <a href="{{ route('property-booking.pob.terminal') }}" class="btn btn-primary"><i class="ti ti-device-desktop me-2"></i>Point of Booking</a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.booking-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
