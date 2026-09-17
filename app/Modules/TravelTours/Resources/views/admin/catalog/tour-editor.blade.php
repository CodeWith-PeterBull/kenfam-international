@extends('layouts.dashboard-layout')

@section('title', $tour ? 'Edit Tour' : 'Create Tour')

@push('styles')
    @vite('app/Modules/TravelTours/Resources/assets/css/admin.css')
@endpush

@section('content')
    <main class="travel-admin">
        <div class="page-header">
            <div class="page-title">
                <h4>{{ $tour ? 'Edit tour' : 'Create tour' }}</h4>
                <h6>{{ $tour ? $tour->name.' | '.$tour->code : 'Start with a clear identity, operating profile, and booking policy' }}</h6>
            </div>
            <div class="page-btn">
                <a href="{{ route('travel-tours.admin.catalog.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Tour catalog</a>
            </div>
        </div>
        <livewire:travel-tours.admin.tour-editor :tour-id="$tour?->id" />
    </main>
@endsection
