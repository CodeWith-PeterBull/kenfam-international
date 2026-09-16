@extends('layouts.dashboard-layout')

@section('title', 'Tour Categories')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Tour categories</h4><h6>Organize the tour catalog for discovery</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a href="{{ route('travel-tours.admin.catalog.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour catalog</a>
                <a href="{{ route('travel-tours.admin.catalog.destinations') }}" class="btn btn-outline-secondary"><i class="ti ti-map-pin me-2" aria-hidden="true"></i>Destinations</a>
            </div>
        </div>
        <livewire:travel-tours.admin.tour-category-manager />
    </main>
@endsection
