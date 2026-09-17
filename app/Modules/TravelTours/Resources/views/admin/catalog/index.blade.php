@extends('layouts.dashboard-layout')

@section('title', 'Tour Catalog')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title">
                <h4>Tour catalog</h4>
                <h6>Build tour products, organize their discovery, and define each route</h6>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('travel-tours.admin.catalog.categories') }}" class="btn btn-outline-secondary"><i class="ti ti-category me-2" aria-hidden="true"></i>Categories</a>
                <a href="{{ route('travel-tours.admin.catalog.destinations') }}" class="btn btn-outline-secondary"><i class="ti ti-map-pin me-2" aria-hidden="true"></i>Destinations</a>
            </div>
        </div>
        <livewire:travel-tours.admin.tour-catalog />
    </main>
@endsection
