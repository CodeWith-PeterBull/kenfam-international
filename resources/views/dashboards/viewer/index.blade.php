@extends('layouts.dashboard-layout')

@section('title', 'Viewer dashboard')

@if (config('commerce.enabled', true) && config('commerce.pos.sales_history.dashboard_enabled', true))
    @push('styles')
        @vite('app/Modules/Commerce/Resources/css/cashier-sales-history.css')
    @endpush
@endif

@section('content')
    @include('dashboards.partials.role-overview')
@endsection
