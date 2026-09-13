@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Guests')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-guests" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Guest directory</h4>
                <h6>Reusable contact, address, emergency, and protected identity profiles</h6>
            </div>
            <div class="page-btn">
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_BOOKINGS)
                    <a href="{{ route('property-booking.admin.bookings.index') }}" class="btn btn-outline-secondary"><i class="ti ti-calendar-check me-2"></i>Bookings</a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.guest-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
