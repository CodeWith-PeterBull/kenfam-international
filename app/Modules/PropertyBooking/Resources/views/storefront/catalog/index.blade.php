@extends('property-booking::layouts.storefront')

@section('title', 'Find a stay')
@section('meta_description', 'Search live accommodation availability, compare transparent rates, and reserve directly.')
@section('nav', 'stays')

@section('content')
    <section class="pb-stay-hero"><div class="container-xxl"><div><p class="pb-storefront-eyebrow">Direct accommodation</p><h1>Find your next place to stay.</h1><p>Search real availability across rooms, apartments, suites, and houses with clear local pricing.</p></div><div class="pb-stay-hero__facts" aria-label="Booking benefits"><span><i data-lucide="badge-check" aria-hidden="true"></i>Current availability</span><span><i data-lucide="receipt-text" aria-hidden="true"></i>Transparent totals</span><span><i data-lucide="mail-check" aria-hidden="true"></i>Private confirmation</span></div></div></section>
    @if(session('warning'))<div class="container-xxl pt-4"><div class="pb-storefront-notice pb-storefront-notice--warning" role="alert">{{ session('warning') }}</div></div>@endif
    <livewire:property-booking.storefront.availability-browser />
@endsection
