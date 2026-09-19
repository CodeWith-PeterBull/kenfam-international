@extends('layouts.dashboard-layout')

@section('title', 'Booking Registers')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Booking registers</h4><h6>Desk endpoints, receipt printers, and shift availability</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.pob.admin.shifts.index') }}"><i class="ti ti-clock-dollar me-2" aria-hidden="true"></i>Booking shifts</a>
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.pob.terminal') }}"><i class="ti ti-device-desktop me-2" aria-hidden="true"></i>Booking desk</a>
            </div>
        </div>
        <livewire:travel-tours.pob.admin.register-manager />
    </main>
@endsection
