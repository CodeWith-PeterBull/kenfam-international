@extends($reportContext->orientation->layout())
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@section('content')
    @php($money = static fn (int $minor): string => MoneyFormatter::format($minor, $receipt->currency, $receipt->currencyExponent))
    <table class="report-meta-table">
        <tr><td><strong>Booking</strong><br>{{ $receipt->bookingNumber }}</td><td><strong>Status</strong><br>{{ $receipt->bookingStatusLabel }}</td><td><strong>Booked</strong><br>{{ $receipt->placedAt->timezone($receipt->departureTimezone)->format('d M Y, H:i') }}</td></tr>
        <tr><td><strong>Customer</strong><br>{{ $receipt->customerName }}@if ($receipt->customerContact)<br>{{ $receipt->customerContact }}@endif</td><td><strong>Tour</strong><br>{{ $receipt->tourName }} ({{ $receipt->tourCode }})</td><td><strong>Travellers</strong><br>{{ $receipt->travellerCount }}</td></tr>
        <tr><td colspan="2"><strong>Travel window</strong><br>{{ $receipt->startsAt->timezone($receipt->departureTimezone)->format('d M Y') }} - {{ $receipt->endsAt->timezone($receipt->departureTimezone)->format('d M Y') }}</td><td><strong>Register</strong><br>{{ $receipt->registerName }} ({{ $receipt->registerCode }})<br>{{ $receipt->operatorName }}</td></tr>
    </table>
    <h3>Fares</h3>
    <table class="report-table">
        <thead><tr><th>Description</th><th>Quantity</th><th class="amount">Unit</th><th class="amount">Total</th></tr></thead>
        <tbody>
            @foreach ($receipt->lines as $line)
                <tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td class="amount">{{ $money($line->unitMinor) }}</td><td class="amount">{{ $money($line->totalMinor) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <h3>Totals</h3>
    <table class="report-table">
        <tbody>
            <tr><td><strong>Fares</strong></td><td class="amount">{{ $money($receipt->subtotalMinor) }}</td></tr>
            @if ($receipt->discountMinor > 0)
                <tr><td><strong>Discount</strong></td><td class="amount">-{{ $money($receipt->discountMinor) }}</td></tr>
            @endif
            <tr><td><strong>Tax</strong></td><td class="amount">{{ $money($receipt->taxMinor) }}</td></tr>
            <tr><td><strong>Total</strong></td><td class="amount"><strong>{{ $money($receipt->totalMinor) }}</strong></td></tr>
            <tr><td><strong>Paid</strong></td><td class="amount">{{ $money($receipt->paidMinor) }}</td></tr>
            <tr><td><strong>Balance due</strong></td><td class="amount"><strong>{{ $money($receipt->balanceMinor) }}</strong></td></tr>
        </tbody>
    </table>
    <h3>Payment tenders</h3>
    <table class="report-table">
        <thead><tr><th>Method</th><th>Reference</th><th class="amount">Amount</th><th class="amount">Change</th></tr></thead>
        <tbody>
            @foreach ($receipt->payments as $payment)
                <tr><td>{{ $payment->methodLabel }}</td><td>{{ $payment->reference ?: 'Not applicable' }}</td><td class="amount">{{ $money($payment->amountMinor) }}</td><td class="amount">{{ $money($payment->changeMinor) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endsection
