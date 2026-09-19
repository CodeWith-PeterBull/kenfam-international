@extends('layouts.dashboard-layout')

@section('title', 'Booking Shifts')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Booking shifts</h4><h6>Open staffed desks and reconcile physical cash at close</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.pob.admin.registers.index') }}"><i class="ti ti-cash-register me-2" aria-hidden="true"></i>Booking registers</a>
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.pob.terminal') }}"><i class="ti ti-device-desktop me-2" aria-hidden="true"></i>Booking desk</a>
            </div>
        </div>
        <livewire:travel-tours.pob.admin.shift-manager />
    </main>
@endsection
