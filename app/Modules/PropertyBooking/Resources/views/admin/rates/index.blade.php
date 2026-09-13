@extends('layouts.dashboard-layout')

@section('title', 'Accommodation Rates')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main id="property-booking-rates" class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title">
                <h4>Accommodation rates</h4>
                <h6>Hourly, day-use, and nightly plans with tax, deposits, restrictions, and date overrides</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('property-booking.admin.units.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-bed me-2"></i>Units
                </a>
                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY)
                    <a href="{{ route('property-booking.admin.availability.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-calendar-search me-2"></i>Quote availability
                    </a>
                @endcan
            </div>
        </div>

        <livewire:property-booking.admin.rate-plan-manager />
        <livewire:property-booking.admin.rate-override-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
