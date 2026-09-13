@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Amenities')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-amenities" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Accommodation amenities</h4>
                <h6>Reusable, filterable facilities for properties and unit types</h6>
            </div>
            <div class="page-btn">
                <a href="{{ route('property-booking.admin.properties.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-building-estate me-2"></i>Properties
                </a>
            </div>
        </div>

        <livewire:property-booking.admin.amenity-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
