@extends('travel-tours::layouts.storefront')

@php
    $profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current();
    $localStart = $booking->departure_starts_at_snapshot->timezone($booking->departure_timezone_snapshot);
    $localEnd = $booking->departure_ends_at_snapshot->timezone($booking->departure_timezone_snapshot);
@endphp

@section('title', ($isConfirmation ? 'Booking received' : 'Track booking').' '.$booking->booking_number)
@section('meta_description', 'Private booking status for '.$booking->booking_number.'.')
@section('canonical', url()->current())
@section('robots', 'noindex, nofollow, noarchive')
@section('page', 'booking-status')

@section('content')
<section class="travel-catalog-hero" aria-labelledby="booking-status-title">
    <div class="container-xxl">
        <p class="travel-eyebrow">{{ $isConfirmation ? 'Booking received' : 'Booking status' }}</p>
        <h1 id="booking-status-title">{{ $booking->booking_number }}</h1>
        <p>{{ $isConfirmation ? 'Your journey request has been recorded. Keep this private link available for current status information.' : 'This private page contains the latest recorded status for your journey.' }}</p>
    </div>
</section>

<section class="travel-section">
    <div class="container-xxl travel-detail-grid">
        <div class="travel-detail-content">
            <section aria-labelledby="booking-journey-title">
                <span class="travel-status-badge"><i data-lucide="circle-check" aria-hidden="true"></i>{{ $booking->status->label() }}</span>
                <p class="travel-eyebrow mt-4">Journey details</p>
                <h2 id="booking-journey-title">{{ $booking->tour_name_snapshot }}</h2>
                <dl class="travel-booking-summary">
                    <div><dt>Lead customer</dt><dd>{{ $booking->customer_name_snapshot }}</dd></div>
                    <div><dt>Travel dates</dt><dd>{{ $localStart->format('d M Y') }} to {{ $localEnd->format('d M Y') }}</dd></div>
                    <div><dt>Travelers</dt><dd>{{ $booking->participants->count() }} {{ Str::plural('traveler', $booking->participants->count()) }}</dd></div>
                    <div><dt>Booking status</dt><dd>{{ $booking->status->label() }}</dd></div>
                    <div><dt>Payment status</dt><dd>{{ $booking->payment_status->label() }}</dd></div>
                    <div><dt>Amount received</dt><dd>{{ \App\Modules\TravelTours\Support\MoneyFormatter::format($booking->paid_minor, $booking->currency, $booking->currency_exponent) }} of {{ \App\Modules\TravelTours\Support\MoneyFormatter::format($booking->total_minor, $booking->currency, $booking->currency_exponent) }}</dd></div>
                </dl>
            </section>
        </div>

        <aside class="travel-booking-panel" aria-labelledby="booking-help-title">
            <p class="travel-eyebrow">Need help?</p>
            <h2 id="booking-help-title">Our team is here.</h2>
            <p class="travel-copy">Quote your booking number whenever you contact the travel desk.</p>
            @if ($profile->whatsappNumber)
                <a class="travel-button travel-button--wide mt-4" href="https://wa.me/{{ $profile->whatsappNumber }}?text={{ rawurlencode('Hello '.$profile->shortName.', I need help with booking '.$booking->booking_number) }}" target="_blank" rel="noopener noreferrer"><i data-lucide="message-circle" aria-hidden="true"></i>Ask on WhatsApp</a>
            @endif
            @if ($profile->email)
                <a class="travel-button travel-button--quiet travel-button--wide mt-2" href="mailto:{{ $profile->email }}?subject={{ rawurlencode('Booking '.$booking->booking_number) }}"><i data-lucide="mail" aria-hidden="true"></i>Email the travel desk</a>
            @endif
        </aside>
    </div>
</section>
@endsection
