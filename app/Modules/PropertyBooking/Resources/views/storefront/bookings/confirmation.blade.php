@extends('property-booking::layouts.storefront')

@section('title', 'Booking received')
@section('meta_description', 'Your accommodation booking has been received.')
@section('nav', 'booking')

@push('head')<meta name="robots" content="noindex, nofollow, noarchive">@endpush

@section('content')
    <section class="pb-stay-booking-hero pb-stay-booking-hero--success"><div class="container-xxl"><span class="pb-stay-booking-hero__icon"><i data-lucide="check" aria-hidden="true"></i></span><p class="pb-storefront-eyebrow">Booking received</p><h1>Thank you, {{ $booking->guest_first_name }}.</h1><p>Booking <strong>{{ $booking->booking_number }}</strong> for <strong>{{ $booking->property_name }}</strong> was placed successfully. A private confirmation is being sent to {{ $booking->guest_email }}.</p><div class="pb-stay-booking-actions"><a class="pb-storefront-button" href="{{ $trackingUrl }}">Track booking <i data-lucide="route" aria-hidden="true"></i></a><a class="pb-storefront-button pb-storefront-button--secondary" href="{{ $portraitDocumentUrl }}" target="_blank" rel="noopener">Portrait summary <i data-lucide="file-text" aria-hidden="true"></i></a><a class="pb-storefront-text-link" href="{{ $landscapeDocumentUrl }}" target="_blank" rel="noopener">Landscape PDF</a></div><dl class="pb-stay-booking-hero__dates"><div><dt>Arrival</dt><dd>{{ $booking->starts_at->timezone($booking->property_timezone)->format('D, d M Y, H:i') }}</dd></div><div><dt>Departure</dt><dd>{{ $booking->ends_at->timezone($booking->property_timezone)->format('D, d M Y, H:i') }}</dd></div></dl></div></section>
    <section class="pb-stay-public-booking"><div class="container-xxl">@include('property-booking::storefront.bookings.partials.details')</div></section>
@endsection
