@extends('layouts.dashboard-layout')

@section('title', 'Commerce Orders')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Orders</h4>
            <h6>Storefront and point-of-sale fulfillment, payments, and documents</h6>
        </div>
        <div class="page-btn d-flex flex-wrap gap-2">
            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_CUSTOMERS)
                <a href="{{ route('commerce.admin.customers.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-user-dollar me-2"></i>Customers
                </a>
            @endcan
            <a href="{{ route('commerce.storefront.catalog.index') }}" class="btn btn-primary" target="_blank" rel="noopener">
                <i class="ti ti-world me-2"></i>Open store
            </a>
        </div>
    </div>

    <livewire:commerce.admin.order-manager />
@endsection
