@extends('layouts.dashboard-layout')

@section('title', 'Inventory')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Inventory</h4>
            <h6>Current stock balances, controlled adjustments, and movement history</h6>
        </div>
        @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_PRODUCTS)
            <div class="page-btn">
                <a href="{{ route('commerce.admin.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-package me-2"></i>Product catalog
                </a>
            </div>
        @endcan
    </div>

    <livewire:commerce.admin.inventory-manager />
@endsection
