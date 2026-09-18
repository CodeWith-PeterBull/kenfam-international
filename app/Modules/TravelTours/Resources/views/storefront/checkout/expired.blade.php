@extends('travel-tours::layouts.storefront')

@php
    $tour = $hold->departure->tour;
@endphp

@section('title', 'Your held places have been released')
@section('meta_description', 'The time allowed to complete this booking has passed.')
@section('canonical', url()->current())
@section('robots', 'noindex, nofollow, noarchive')
@section('page', 'checkout-expired')

@section('content')
<section class="travel-catalog-hero" aria-labelledby="checkout-expired-title">
    <div class="container-xxl">
        <p class="travel-eyebrow">Time ran out</p>
        <h1 id="checkout-expired-title">Your held places have been released</h1>
        <p>Places are held for {{ (int) config('travel-tours.booking.hold_minutes', 15) }} minutes so that other travellers are not kept waiting. Nothing has been booked or charged.</p>
    </div>
</section>

<section class="travel-section">
    <div class="container-xxl">
        <div class="travel-empty">
            <i data-lucide="hourglass" aria-hidden="true"></i>
            <h2>Start again in a moment</h2>
            <p>Return to {{ $tour->name }}, choose your departure and travellers again, and your places will be held afresh.</p>
            <a class="travel-button" href="{{ route('travel-tours.storefront.tours.show', $tour->slug) }}#tour-departures-title">Back to {{ $tour->name }} <i data-lucide="arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>
</section>
@endsection
