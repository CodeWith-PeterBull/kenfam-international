@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Availability')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-availability" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Availability and quotes</h4>
                <h6>Advisory pricing, concrete inventory checks, and auditable operational blocks</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('property-booking.admin.units.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-bed me-2"></i>Units
                </a>
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_RATES)
                    <a href="{{ route('property-booking.admin.rates.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-receipt-2 me-2"></i>Rates
                    </a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.availability-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
