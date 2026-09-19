@extends('travel-tours::layouts.pob')
@use('App\Modules\TravelTours\Support\MoneyFormatter')

@section('title', 'Booking Receipt '.$receipt->bookingNumber)

@push('styles')
    <style>@media print { @page { size: auto; margin: 4mm; } .pob-receipt { width: {{ $printInstruction->paperWidth->printableWidth() }}mm !important; } }</style>
@endpush

@section('content')
    @php($money = static fn (int $minor): string => MoneyFormatter::format($minor, $receipt->currency, $receipt->currencyExponent))
    <div class="pob-workspace pob-receipt-page">
        <div class="pob-receipt-actions">
            <a class="pob-button pob-button--secondary" href="{{ route('travel-tours.pob.terminal') }}"><i class="ti ti-arrow-left" aria-hidden="true"></i>New booking</a>
            <div>
                <button class="pob-button pob-button--secondary" type="button" data-pob-print><i class="ti ti-printer" aria-hidden="true"></i>Print</button>
                <a class="pob-button" href="{{ $receiptPdfUrl }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf" aria-hidden="true"></i>PDF receipt</a>
            </div>
        </div>

        <article class="pob-receipt" data-pob-receipt data-pob-paper-width="{{ $printInstruction->paperWidth->value }}" aria-labelledby="receipt-title">
            <header class="pob-receipt__brand">
                <img src="{{ $profile->logoUrl }}" alt="{{ $profile->shortName }}">
                <div><h1 id="receipt-title">Booking receipt</h1><p>{{ $receipt->bookingNumber }}</p></div>
            </header>
            <section class="pob-receipt__meta" aria-label="Booking details">
                <div><span>Booking date</span><strong>{{ $receipt->placedAt->timezone($receipt->departureTimezone)->format('d M Y, H:i') }}</strong></div>
                <div><span>Status</span><strong>{{ $receipt->bookingStatusLabel }}</strong></div>
                <div><span>Tour</span><strong>{{ $receipt->tourName }} &middot; {{ $receipt->tourCode }}</strong></div>
                <div><span>Travel dates</span><strong>{{ $receipt->startsAt->timezone($receipt->departureTimezone)->format('d M Y') }} &ndash; {{ $receipt->endsAt->timezone($receipt->departureTimezone)->format('d M Y') }}</strong></div>
                <div><span>Register</span><strong>{{ $receipt->registerName }} &middot; {{ $receipt->registerCode }}</strong></div>
                <div><span>Operator</span><strong>{{ $receipt->operatorName }}</strong></div>
                <div><span>Customer</span><strong>{{ $receipt->customerName }}@if ($receipt->customerContact) &middot; {{ $receipt->customerContact }}@endif</strong></div>
                <div><span>Travellers</span><strong>{{ $receipt->travellerCount }}</strong></div>
            </section>
            <table>
                <thead><tr><th>Fare</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach ($receipt->lines as $line)
                        <tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $money($line->totalMinor) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <dl class="pob-receipt__totals">
                <div><dt>Fares</dt><dd>{{ $money($receipt->subtotalMinor) }}</dd></div>
                @if ($receipt->discountMinor > 0)
                    <div><dt>Discount</dt><dd>&minus;{{ $money($receipt->discountMinor) }}</dd></div>
                @endif
                <div><dt>Tax</dt><dd>{{ $money($receipt->taxMinor) }}</dd></div>
                <div><dt>Total</dt><dd>{{ $money($receipt->totalMinor) }}</dd></div>
            </dl>
            <section class="pob-receipt__payments" aria-labelledby="receipt-payment-title">
                <h2 id="receipt-payment-title">Payment</h2>
                @foreach ($receipt->payments as $payment)
                    <div><span>{{ $payment->methodLabel }}@if ($payment->reference) &middot; {{ $payment->reference }}@endif</span><strong>{{ $money($payment->amountMinor) }}</strong></div>
                    @if ($payment->changeMinor > 0)
                        <div><span>Cash change</span><strong>{{ $money($payment->changeMinor) }}</strong></div>
                    @endif
                @endforeach
                <div><span>Paid</span><strong>{{ $money($receipt->paidMinor) }}</strong></div>
                <div><span>Balance due</span><strong>{{ $money($receipt->balanceMinor) }}</strong></div>
            </section>
            <footer class="pob-receipt__footer">
                <strong>Thank you. We look forward to travelling with you.</strong>
                <p>{{ $profile->name }}@if ($profile->phone) &middot; {{ $profile->phone }}@endif @if ($profile->email) &middot; {{ $profile->email }}@endif</p>
            </footer>
        </article>
        <script type="application/json" data-pob-print-instruction>@json($printInstruction->toBrowserArray())</script>
    </div>
@endsection
