@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $booking = $payment->booking;
    $register = $payment->shift?->register;
    $width = in_array((int) ($register?->receipt_paper_width_mm ?? 80), [58, 80], true) ? (int) ($register?->receipt_paper_width_mm ?? 80) : 80;
    $money = static fn (int $minor): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
    $outstanding = max($booking->total_minor - ($booking->paid_minor - $booking->refunded_minor), 0);
    $timezone = $booking->departure_timezone_snapshot;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Receipt {{ $payment->reference ?: $booking->booking_number }}</title>
    <style>
        :root { --receipt-width: {{ $width }}mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #f3f4f5; color: #111; font: 12px/1.45 "Courier New", Courier, monospace; }
        .receipt { width: var(--receipt-width); margin: 16px auto; padding: 6mm 4mm; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); }
        .receipt h1 { margin: 0 0 2px; font-size: 14px; text-align: center; text-transform: uppercase; letter-spacing: .04em; }
        .receipt p { margin: 0; }
        .receipt .center { text-align: center; }
        .receipt .muted { color: #555; }
        .receipt hr { border: 0; border-top: 1px dashed #888; margin: 6px 0; }
        .receipt table { width: 100%; border-collapse: collapse; }
        .receipt td { padding: 1px 0; vertical-align: top; }
        .receipt td.amount { text-align: right; white-space: nowrap; }
        .receipt .total td { font-weight: 700; }
        .actions { width: var(--receipt-width); margin: 0 auto 24px; display: flex; gap: 8px; }
        .actions button, .actions a { flex: 1; padding: 8px 10px; font: 600 13px/1 system-ui, sans-serif; border: 1px solid #70233a; border-radius: 4px; background: #70233a; color: #fff; cursor: pointer; text-align: center; text-decoration: none; }
        .actions a { background: #fff; color: #70233a; }
        @media print {
            html, body { background: #fff; }
            .receipt { margin: 0; box-shadow: none; width: var(--receipt-width); }
            .actions { display: none; }
            @page { size: var(--receipt-width) auto; margin: 0; }
        }
    </style>
</head>
<body>
    <article class="receipt" aria-label="Payment receipt">
        <h1>{{ $profile->shortName ?: $profile->name }}</h1>
        <p class="center muted">{{ $profile->name }}</p>
        @if ($profile->phone)<p class="center muted">{{ $profile->phone }}</p>@endif
        <hr>
        <p><strong>Receipt</strong> {{ $payment->reference ?: $payment->ulid }}</p>
        <p>{{ $payment->paid_at?->timezone($timezone)->format('d M Y H:i') }}</p>
        <p>Booking {{ $booking->booking_number }}</p>
        <p>{{ $booking->customer_name_snapshot }}</p>
        @if ($register)<p class="muted">Register {{ $register->code }} &middot; {{ $payment->receiver?->name }}</p>@endif
        <hr>
        <p><strong>{{ $booking->tour_name_snapshot }}</strong></p>
        <p>{{ $booking->departure_starts_at_snapshot->timezone($timezone)->format('d M Y') }} to {{ $booking->departure_ends_at_snapshot->timezone($timezone)->format('d M Y') }}</p>
        <table>
            @foreach ($booking->participants->sortBy('sequence') as $participant)
                <tr><td>{{ trim($participant->first_name.' '.$participant->last_name) }} ({{ $participant->participant_type->label() }})</td><td class="amount">{{ $money((int) $participant->allocated_price_minor) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table>
            <tr><td>Booking total</td><td class="amount">{{ $money((int) $booking->total_minor) }}</td></tr>
            <tr class="total"><td>Paid now ({{ $payment->method->label() }})</td><td class="amount">{{ $money((int) $payment->amount_minor) }}</td></tr>
            <tr><td>Paid to date</td><td class="amount">{{ $money((int) $booking->paid_minor) }}</td></tr>
            <tr><td>Balance due</td><td class="amount">{{ $money($outstanding) }}</td></tr>
        </table>
        <hr>
        <p class="center">Booking status: {{ $booking->status->label() }}</p>
        <p class="center muted">Thank you for travelling with us.</p>
    </article>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('travel-tours.pob.terminal') }}">Back to desk</a>
    </div>
    @if ($autoPrint)
        <script>
            // Print once per receipt per browser session; a reload never re-prints.
            (function () {
                var key = 'travel-receipt-printed-{{ $payment->ulid }}';
                if (sessionStorage.getItem(key)) return;
                sessionStorage.setItem(key, '1');
                window.addEventListener('load', function () { window.setTimeout(function () { window.print(); }, 150); });
            }());
        </script>
    @endif
</body>
</html>
