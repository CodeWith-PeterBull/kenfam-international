@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Properties')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-properties" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Accommodation properties</h4>
                <h6>Establishments, operating policy, staff scope, amenities, and media</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY)
                    <a href="{{ route('property-booking.admin.availability.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-calendar-search me-2"></i>Availability
                    </a>
                @endcan
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_RATES)
                    <a href="{{ route('property-booking.admin.rates.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-receipt-2 me-2"></i>Rates
                    </a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.property-manager />
        <livewire:property-booking.admin.property-category-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
