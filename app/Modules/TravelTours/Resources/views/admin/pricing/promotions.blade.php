@extends('layouts.dashboard-layout')

@section('title', 'Promotions')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>Promotions</h4><h6>Codes customers enter on the tour page</h6></div>
            <div class="page-btn"><a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.pricing.index') }}"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour pricing</a></div>
        </div>
        <livewire:travel-tours.admin.promotion-manager />
    </main>
@endsection
