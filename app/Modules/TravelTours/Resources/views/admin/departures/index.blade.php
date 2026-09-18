@extends('layouts.dashboard-layout')

@section('title', 'Tour Departures')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Tour departures</h4><h6>Schedules, availability and operating state</h6></div>
            <div class="page-btn"><a href="{{ route('travel-tours.admin.catalog.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour catalog</a></div>
        </div>
        <livewire:travel-tours.admin.departure-manager />
    </main>
@endsection
