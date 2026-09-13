@extends('property-booking::layouts.pob')

@section('title', 'Booking Receipt '.$receipt->bookingNumber)

@push('styles')
    <style>@media print { @page { size: auto; margin: 4mm; } .pob-receipt { width: {{ $printInstruction->paperWidth->printableWidth() }}mm !important; } }</style>
@endpush

@section('content')
    <div class="pob-workspace pob-receipt-page">
        <div class="pob-receipt-actions">
            <a class="pob-button pob-button--secondary" href="{{ route('property-booking.pob.terminal') }}"><i class="ti ti-arrow-left" aria-hidden="true"></i>New booking</a>
            <div><button class="pob-button pob-button--secondary" type="button" data-pob-print><i class="ti ti-printer" aria-hidden="true"></i>Print</button><a class="pob-button" href="{{ $receiptPdfUrl }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf" aria-hidden="true"></i>PDF receipt</a></div>
        </div>

        <article class="pob-receipt" data-pob-receipt data-pob-paper-width="{{ $printInstruction->paperWidth->value }}" aria-labelledby="receipt-title">
            <header class="pob-receipt__brand"><img src="{{ $institutionProfile->mainLogoUrl ?: asset('aureon/assets/brand/logo.png') }}" alt="{{ $institutionProfile->shortName }}"><div><h1 id="receipt-title">Booking receipt</h1><p>{{ $receipt->bookingNumber }}</p></div></header>
            <section class="pob-receipt__meta" aria-label="Booking details">
                <div><span>Booking date</span><strong>{{ $receipt->placedAt->format('d M Y, H:i') }}</strong></div>
                <div><span>Property</span><strong>{{ $receipt->propertyName }}</strong></div>
                <div><span>Stay</span><strong>{{ $receipt->startsAt->timezone($receipt->propertyTimezone)->format('d M Y, H:i') }} - {{ $receipt->endsAt->timezone($receipt->propertyTimezone)->format('d M Y, H:i') }}</strong></div>
                <div><span>Register</span><strong>{{ $receipt->registerName }} &middot; {{ $receipt->registerCode }}</strong></div>
                <div><span>Receptionist</span><strong>{{ $receipt->receptionistName }}</strong></div>
                <div><span>Guest</span><strong>{{ $receipt->guestName }}@if($receipt->guestContact) &middot; {{ $receipt->guestContact }}@endif</strong></div>
            </section>
            <table><thead><tr><th>Accommodation</th><th>Duration</th><th>Guests</th><th>Total</th></tr></thead><tbody>@foreach($receipt->stays as $stay)<tr><td><strong>{{ $stay->unitTypeName }}</strong><br><small>{{ $stay->ratePlanName }}</small></td><td>{{ $stay->billableUnits }} {{ \Illuminate\Support\Str::plural($stay->pricingUnitLabel, $stay->billableUnits) }}</td><td>{{ $stay->occupantCount }}</td><td>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($stay->totalMinor, $receipt->currency) }}</td></tr>@endforeach</tbody></table>
            <dl class="pob-receipt__totals"><div><dt>Accommodation</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($receipt->accommodationSubtotalMinor, $receipt->currency) }}</dd></div>@if($receipt->chargesSubtotalMinor > 0)<div><dt>Additional charges</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($receipt->chargesSubtotalMinor, $receipt->currency) }}</dd></div>@endif @if($receipt->discountMinor > 0)<div><dt>Discount</dt><dd>-{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($receipt->discountMinor, $receipt->currency) }}</dd></div>@endif<div><dt>Tax {{ $receipt->taxInclusive ? 'included' : '' }}</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($receipt->taxMinor, $receipt->currency) }}</dd></div><div><dt>Total</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($receipt->totalMinor, $receipt->currency) }}</dd></div></dl>
            <section class="pob-receipt__payments" aria-labelledby="receipt-payment-title"><h2 id="receipt-payment-title">Payment</h2>@foreach($receipt->payments as $payment)<div><span>{{ $payment->methodLabel }}@if($payment->reference) &middot; {{ $payment->reference }}@endif</span><strong>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($payment->amountMinor, $receipt->currency) }}</strong></div>@if($payment->changeMinor > 0)<div><span>Cash change</span><strong>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($payment->changeMinor, $receipt->currency) }}</strong></div>@endif @endforeach</section>
            <footer class="pob-receipt__footer"><strong>Thank you. We look forward to welcoming you.</strong><p>{{ $institutionProfile->name }}@if($institutionProfile->primaryPhone) &middot; {{ $institutionProfile->primaryPhone }}@endif @if($institutionProfile->primaryEmail) &middot; {{ $institutionProfile->primaryEmail }}@endif</p></footer>
        </article>
        <script type="application/json" data-pob-print-instruction>@json($printInstruction->toBrowserArray())</script>
    </div>
@endsection
