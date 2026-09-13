@extends('property-booking::layouts.storefront')

@section('title', 'Complete your booking')
@section('meta_description', 'Enter guest details and place your selected accommodation booking.')
@section('nav', 'checkout')

@section('content')
    <section class="pb-stay-process-hero"><div class="container-xxl"><p class="pb-storefront-eyebrow">Step 2 of 2</p><h1>Complete your booking</h1><p>Your total and availability remain server verified. Payment is arranged after placement.</p></div></section>
    <section class="pb-stay-checkout"><div class="container-xxl"><livewire:property-booking.storefront.checkout /></div></section>
@endsection
