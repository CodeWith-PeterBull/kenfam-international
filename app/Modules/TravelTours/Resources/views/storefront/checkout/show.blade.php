@extends('travel-tours::layouts.storefront')

@php
    $tour = $hold->departure->tour;
    $localStart = $hold->departure->starts_at->timezone($hold->departure->timezone);
@endphp

@section('title', 'Complete your booking')
@section('meta_description', 'Enter traveller details to complete your booking for '.$tour->name.'.')
@section('canonical', url()->current())
@section('robots', 'noindex, nofollow, noarchive')
@section('page', 'checkout')

@section('content')
<section class="travel-catalog-hero" aria-labelledby="checkout-title">
    <div class="container-xxl">
        <p class="travel-eyebrow">Almost there</p>
        <h1 id="checkout-title">Complete your booking</h1>
        <p>{{ $tour->name }}, departing {{ $localStart->format('D d M Y') }}. Your places are held while you enter traveller details; nothing is paid online.</p>
    </div>
</section>

<section class="travel-section">
    <div class="container-xxl">
        <livewire:travel-tours.storefront.booking-checkout :hold="$hold" />
    </div>
</section>
@endsection
