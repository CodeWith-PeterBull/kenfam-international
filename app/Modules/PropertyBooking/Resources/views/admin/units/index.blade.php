@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Inventory')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-units" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Accommodation inventory</h4>
                <h6>Sellable unit types, physical rooms and houses, readiness, and galleries</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('property-booking.admin.properties.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-building-estate me-2"></i>Properties
                </a>
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY)
                    <a href="{{ route('property-booking.admin.availability.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-calendar-search me-2"></i>Availability
                    </a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.unit-type-manager />
        <livewire:property-booking.admin.accommodation-unit-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
