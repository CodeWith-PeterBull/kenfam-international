@extends('layouts.dashboard-layout')

@section('title', 'Commerce demo data')

@push('styles')
    @vite('app/Modules/Commerce/Resources/css/admin-dashboard.css')
@endpush

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Commerce demo data</h4>
            <h6>Select and seed one prepared merchant catalog through the canonical Commerce command</h6>
        </div>
        @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_DASHBOARD)
            <div class="page-btn">
                <a href="{{ route('commerce.admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-2" aria-hidden="true"></i>Commerce overview
                </a>
            </div>
        @endcan
    </div>

    <livewire:commerce.admin.demo-data-manager />
@endsection
