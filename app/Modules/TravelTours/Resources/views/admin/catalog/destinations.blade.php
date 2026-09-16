@extends('layouts.dashboard-layout')

@section('title', 'Tour Destinations')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Destinations</h4><h6>Geography, editorial detail, and destination imagery</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('travel-tours.admin.catalog.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour catalog</a>
                <a href="{{ route('travel-tours.admin.catalog.categories') }}" class="btn btn-outline-secondary"><i class="ti ti-category me-2" aria-hidden="true"></i>Categories</a>
            </div>
        </div>
        <livewire:travel-tours.admin.destination-manager />
    </main>
@endsection
