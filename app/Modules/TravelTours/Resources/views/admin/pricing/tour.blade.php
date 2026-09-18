@extends('layouts.dashboard-layout')

@section('title', 'Pricing: '.$tour->name)

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title"><h4>{{ $tour->name }}</h4><h6>{{ $tour->code }} &middot; rate plans, fares, and pricing rules</h6></div>
            <div class="page-btn d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.tours.edit', $tour) }}"><i class="ti ti-pencil me-2" aria-hidden="true"></i>Edit tour</a>
                <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.pricing.index') }}"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>All tours</a>
            </div>
        </div>
        <livewire:travel-tours.admin.rate-plan-manager :tour-id="$tour->getKey()" />
        <livewire:travel-tours.admin.pricing-rule-manager :tour-id="$tour->getKey()" />
    </main>
@endsection
