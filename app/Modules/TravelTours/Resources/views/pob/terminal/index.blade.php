@extends('layouts.dashboard-layout')
@section('title', 'Travel Booking Desk')
@section('content')
<main><div class="page-header"><div class="page-title"><h4>Travel booking desk</h4><h6>Assisted tour selection, traveler capture, settlement, and receipt handling</h6></div></div>
@if($shift)<div class="alert alert-success"><i class="ti ti-clock-check me-2"></i>Active shift on {{ $shift->register->name }}, opened {{ $shift->opened_at->diffForHumans() }}.</div>@else<div class="alert alert-warning"><i class="ti ti-alert-circle me-2"></i>No booking-desk shift is open for your account. A travel manager must open or assign a shift before taking payment.</div>@endif
<section class="card aureon-panel"><div class="card-body py-5 text-center"><i class="ti ti-map-search fs-1 text-primary"></i><h2 class="mt-3">Assisted booking workspace</h2><p class="text-muted mx-auto" style="max-width: 620px">The terminal boundary, operator session check, register configuration, and booking transaction services are ready. The focused Livewire selection and checkout surface is scheduled in phase K6.</p><a class="btn btn-primary" href="{{ route('travel-tours.admin.bookings.index') }}">Review bookings</a></div></section></main>
@endsection
