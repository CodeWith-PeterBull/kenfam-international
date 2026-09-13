@extends('layouts.dashboard-layout')

@section('title', 'Print Barcodes')

@section('content')
    <div class="page-header">
        <div class="page-title">
            <h4>Print barcodes</h4>
            <h6>Generate and print Code 128 labels for your products</h6>
        </div>
        <div class="page-btn">
            <a href="{{ route('commerce.admin.catalog.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-2"></i>Back to catalog
            </a>
        </div>
    </div>

    <livewire:commerce.admin.barcode-label-sheet />
@endsection
