@extends('layouts.dashboard-layout')

@section('title', 'Commerce Customers')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Customers</h4>
            <h6>Reusable customer identities, account links, addresses, and order history</h6>
        </div>
        @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_ORDERS)
            <div class="page-btn">
                <a href="{{ route('commerce.admin.orders.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-receipt me-2"></i>Orders
                </a>
            </div>
        @endcan
    </div>

    <livewire:commerce.admin.customer-manager />
@endsection
