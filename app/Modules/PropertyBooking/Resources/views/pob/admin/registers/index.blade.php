@extends('layouts.dashboard-layout')

@section('title', 'Reception Registers')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title"><h4>Reception registers</h4><h6>Property endpoints, receipt printers, and shift availability</h6></div>
            <ul class="table-top-head">
                <li><a href="{{ route('property-booking.pob.admin.shifts.index') }}" title="Reception shifts"><i class="ti ti-clock-dollar"></i></a></li>
                <li><a href="{{ route('property-booking.pob.terminal') }}" title="Open Point of Booking"><i class="ti ti-device-desktop"></i></a></li>
            </ul>
        </div>

        <livewire:property-booking.pob.admin.reception-register-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
