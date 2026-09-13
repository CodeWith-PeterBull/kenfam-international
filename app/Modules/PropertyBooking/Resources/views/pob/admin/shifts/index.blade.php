@extends('layouts.dashboard-layout')

@section('title', 'Reception Shifts')

@push('styles')
    @vite('app/Modules/PropertyBooking/Resources/css/admin.css')
@endpush

@section('content')
    <main class="property-booking-admin" data-property-booking-admin>
        <div class="page-header">
            <div class="page-title"><h4>Reception shifts</h4><h6>Open staffed desks and reconcile physical cash at close</h6></div>
            <ul class="table-top-head">
                <li><a href="{{ route('property-booking.pob.admin.registers.index') }}" title="Reception registers"><i class="ti ti-building-store"></i></a></li>
                <li><a href="{{ route('property-booking.pob.terminal') }}" title="Open Point of Booking"><i class="ti ti-device-desktop"></i></a></li>
            </ul>
        </div>

        <livewire:property-booking.pob.admin.reception-shift-manager />
    </main>
@endsection

@push('scripts')
    @vite('app/Modules/PropertyBooking/Resources/js/admin.js')
@endpush
