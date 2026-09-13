@extends('layouts.dashboard-layout')

@section('title', 'Product Catalog')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Product catalog</h4>
            <h6>Products, pricing, publication, media, and category structure</h6>
        </div>
        <div class="page-btn d-flex gap-2">
            <a href="{{ route('commerce.admin.catalog.barcodes') }}" class="btn btn-outline-secondary">
                <i class="ti ti-barcode me-2"></i>Print barcodes
            </a>
            @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_INVENTORY)
                <a href="{{ route('commerce.admin.inventory.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-building-warehouse me-2"></i>Inventory
                </a>
            @endcan
        </div>
    </div>

    <livewire:commerce.admin.product-catalog />
    <livewire:commerce.admin.product-category-manager />
@endsection
