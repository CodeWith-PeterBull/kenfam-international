@extends('property-booking::layouts.storefront')

@section('title', 'Track '.$booking->booking_number)
@section('meta_description', 'Track the current booking, stay, and payment status of your accommodation booking.')
@section('nav', 'booking')

@push('head')<meta name="robots" content="noindex, nofollow, noarchive">@endpush

@section('content')
    @php
        $steps = [\App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::Pending, \App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::Confirmed, \App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::Completed];
        $current = array_search($booking->status, $steps, true);
        $terminal = in_array($booking->status, [\App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::Cancelled, \App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::NoShow, \App\Modules\PropertyBooking\Bookings\Enums\BookingStatus::Expired], true);
    @endphp
    <section class="pb-stay-booking-hero {{ $terminal ? 'pb-stay-booking-hero--terminal' : '' }}"><div class="container-xxl"><p class="pb-storefront-eyebrow">Booking tracking</p><h1>{{ $booking->booking_number }}</h1><p>{{ $booking->property_name }} from {{ $booking->starts_at->timezone($booking->property_timezone)->format('d M Y, H:i') }} to {{ $booking->ends_at->timezone($booking->property_timezone)->format('d M Y, H:i') }}.</p><div class="pb-stay-booking-actions"><a class="pb-storefront-button pb-storefront-button--secondary" href="{{ $portraitDocumentUrl }}" target="_blank" rel="noopener">Portrait summary <i data-lucide="file-text" aria-hidden="true"></i></a><a class="pb-storefront-text-link" href="{{ $landscapeDocumentUrl }}" target="_blank" rel="noopener">Landscape PDF</a></div></div></section>
    <section class="pb-stay-tracking" aria-labelledby="booking-progress-title"><div class="container-xxl"><p class="pb-storefront-eyebrow">Current status</p><h2 id="booking-progress-title">{{ $booking->status->label() }}</h2>@if($terminal)<div class="pb-storefront-notice pb-storefront-notice--error"><strong>This booking is no longer active.</strong><span>Please contact the property if you need help with another stay.</span></div>@else<ol>@foreach($steps as $index => $step)<li class="{{ $current !== false && $index <= $current ? 'complete' : '' }} {{ $step === $booking->status ? 'current' : '' }}"><span>{{ $index + 1 }}</span><div><strong>{{ $step->label() }}</strong><small>{{ $step === $booking->status ? 'Current state' : ($current !== false && $index < $current ? 'Completed' : 'Pending') }}</small></div></li>@endforeach</ol>@endif</div></section>
    <section class="pb-stay-public-booking"><div class="container-xxl">@include('property-booking::storefront.bookings.partials.details')</div></section>
@endsection
