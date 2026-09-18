@extends('travel-tours::layouts.storefront')

@use('App\Modules\TravelTours\Bookings\Enums\BookingStatus')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentMethod')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus')
@use('App\Modules\TravelTours\Support\MoneyFormatter')

@php
    $profile = app(\App\Modules\TravelTours\Storefront\Services\TravelStorefrontProfileResolver::class)->current();
    $timezone = $booking->departure_timezone_snapshot;
    $localStart = $booking->departure_starts_at_snapshot->timezone($timezone);
    $localEnd = $booking->departure_ends_at_snapshot->timezone($timezone);
    $money = static fn (int $minor): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
    $confirmedPayments = $booking->payments->where('status', PaymentRecordStatus::Confirmed);
    $pendingPayments = $booking->payments->where('status', PaymentRecordStatus::Pending);
    $outstandingMinor = max($booking->total_minor - $booking->paid_minor, 0);
    $depositOutstandingMinor = max($booking->deposit_required_minor - $booking->paid_minor, 0);
    $awaitingPayment = $booking->paid_minor === 0 && ! $booking->status->isTerminal();
    $methodGuidance = match ($booking->preferred_payment_method) {
        PaymentMethod::MobileMoney => 'Our travel desk will send the mobile money payment details to your phone. Use your booking number as the reference.',
        PaymentMethod::BankTransfer => 'Our travel desk will send the bank account details by email. Quote your booking number on the transfer.',
        PaymentMethod::Card => 'Our travel desk will arrange a secure card payment with you and confirm it here once it is received.',
        PaymentMethod::Cash => 'Pay at our office or with your travel consultant; you will receive a receipt and this page will update.',
        default => 'Our travel desk will contact you to arrange payment and will confirm it here once it is received.',
    };
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
                    <div><dt>Amount received</dt><dd>{{ $money($booking->paid_minor) }} of {{ $money($booking->total_minor) }}</dd></div>
                </dl>
            </section>

            <section class="travel-booking-next" aria-labelledby="booking-next-title">
                <p class="travel-eyebrow">What happens next</p>
                <h2 id="booking-next-title">
                    @if ($booking->status === BookingStatus::Confirmed)
                        Your places are confirmed
                    @elseif ($booking->status->isTerminal())
                        This booking is {{ Str::lower($booking->status->label()) }}
                    @elseif ($awaitingPayment)
                        Secure your places with the deposit
                    @else
                        Your payment is being confirmed
                    @endif
                </h2>
                @if ($booking->status === BookingStatus::Confirmed)
                    <p class="travel-copy">Thank you. Your places on {{ $localStart->format('d M Y') }} are confirmed.@if ($outstandingMinor > 0) The remaining balance of <strong>{{ $money($outstandingMinor) }}</strong> is due before departure; our travel desk will remind you.@endif</p>
                @elseif ($booking->status->isTerminal())
                    <p class="travel-copy">No further action is needed. Contact the travel desk if you would like to book again.</p>
                @else
                    <dl class="travel-booking-summary">
                        <div><dt>Preferred payment</dt><dd>{{ $booking->preferred_payment_method->label() }}</dd></div>
                        @if ($depositOutstandingMinor > 0)
                            <div><dt>Deposit due</dt><dd>{{ $money($depositOutstandingMinor) }}</dd></div>
                        @endif
                        @if ($booking->pending_expires_at)
                            <div><dt>Please pay by</dt><dd>{{ $booking->pending_expires_at->timezone($timezone)->format('d M Y, H:i') }} ({{ $timezone }})</dd></div>
                        @endif
                    </dl>
                    <p class="travel-copy">{{ $methodGuidance }}</p>
                    @if ($pendingPayments->isNotEmpty())
                        <p class="travel-copy"><strong>A payment of {{ $money((int) $pendingPayments->sum('amount_minor')) }} has been recorded and is awaiting confirmation.</strong> This page updates as soon as the travel desk confirms it.</p>
                    @endif
                @endif
            </section>

            <section aria-labelledby="booking-travellers-title">
                <p class="travel-eyebrow">Who is travelling</p>
                <h2 id="booking-travellers-title">Travellers</h2>
                <ol class="travel-booking-travellers">
                    @foreach ($booking->participants->sortBy('sequence') as $participant)
                        <li><strong>{{ trim($participant->first_name.' '.$participant->last_name) }}</strong><span>{{ $participant->participant_type->label() }}@if ($participant->is_lead) &middot; lead traveller @endif</span></li>
                    @endforeach
                </ol>
            </section>

            @if ($confirmedPayments->isNotEmpty())
                <section aria-labelledby="booking-payments-title">
                    <p class="travel-eyebrow">Money received</p>
                    <h2 id="booking-payments-title">Payments</h2>
                    <dl class="travel-booking-summary">
                        @foreach ($confirmedPayments as $payment)
                            <div><dt>{{ $payment->paid_at?->timezone($timezone)->format('d M Y') }}</dt><dd>{{ $money($payment->amount_minor) }} &middot; {{ $payment->method->label() }}@if ($payment->reference) &middot; ref {{ $payment->reference }}@endif</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        <aside class="travel-booking-panel" aria-labelledby="booking-help-title">
            <p class="travel-eyebrow">Keep this handy</p>
            <h2 id="booking-help-title">Your booking documents</h2>
            <p class="travel-copy">Quote your booking number whenever you contact the travel desk.</p>
            <a class="travel-button travel-button--wide mt-4" href="{{ $documentUrl }}" target="_blank" rel="noopener noreferrer"><i data-lucide="file-text" aria-hidden="true"></i>Booking summary (PDF)</a>
            @if ($isConfirmation)
                <a class="travel-button travel-button--quiet travel-button--wide mt-2" href="{{ $trackingUrl }}"><i data-lucide="link" aria-hidden="true"></i>Private tracking link</a>
                <p class="travel-inquiry__assurance"><i data-lucide="shield-check" aria-hidden="true"></i><span>Bookmark the tracking link; it stays valid for {{ (int) config('travel-tours.booking.tracking_link_days', 180) }} days and shows the latest status.</span></p>
            @endif
            @if ($profile->whatsappNumber)
                <a class="travel-button travel-button--quiet travel-button--wide mt-2" href="https://wa.me/{{ $profile->whatsappNumber }}?text={{ rawurlencode('Hello '.$profile->shortName.', I need help with booking '.$booking->booking_number) }}" target="_blank" rel="noopener noreferrer"><i data-lucide="message-circle" aria-hidden="true"></i>Ask on WhatsApp</a>
            @endif
            @if ($profile->email)
                <a class="travel-button travel-button--quiet travel-button--wide mt-2" href="mailto:{{ $profile->email }}?subject={{ rawurlencode('Booking '.$booking->booking_number) }}"><i data-lucide="mail" aria-hidden="true"></i>Email the travel desk</a>
            @endif
        </aside>
    </div>
</section>
@endsection
