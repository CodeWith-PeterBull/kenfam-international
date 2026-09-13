@extends('commerce::layouts.pos')

@section('title', 'Receipt '.$receipt->orderNumber)

@push('styles')
    <style>
        @media print {
            @page { size: auto; margin: 4mm; }
            .pos-receipt {
                width: {{ $printInstruction->paperWidth->printableWidth() }}mm !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="pos-workspace pos-receipt-page">
        <div class="pos-receipt-actions">
            <a class="pos-button pos-button--secondary" href="{{ route('commerce.pos.terminal') }}"><i class="ti ti-arrow-left" aria-hidden="true"></i>New sale</a>
            <div>
                <button class="pos-button pos-button--secondary" type="button" data-pos-print><i class="ti ti-printer" aria-hidden="true"></i>Print</button>
                <a class="pos-button" href="{{ $receiptPdfUrl }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf" aria-hidden="true"></i>PDF receipt</a>
            </div>
        </div>

        <article
            class="pos-receipt"
            data-pos-receipt
            data-pos-paper-width="{{ $printInstruction->paperWidth->value }}"
            aria-labelledby="receipt-title"
        >
            <header class="pos-receipt__brand">
                <img src="{{ $institutionProfile->mainLogoUrl ?: asset('aureon/assets/brand/logo.png') }}" alt="{{ $institutionProfile->shortName }}">
                <div><h1 id="receipt-title">Sales receipt</h1><p>{{ $receipt->orderNumber }}</p></div>
            </header>

            <section class="pos-receipt__meta" aria-label="Sale details">
                <div><span>Date and time</span><strong>{{ $receipt->placedAt?->format('d M Y, H:i') }}</strong></div>
                <div><span>Register</span><strong>{{ $receipt->registerName }} &middot; {{ $receipt->registerCode }}</strong></div>
                <div><span>Cashier</span><strong>{{ $receipt->cashierName }}</strong></div>
                <div><span>Customer</span><strong>{{ $receipt->customerName }}</strong></div>
            </section>

            <table>
                <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach($receipt->lines as $line)
                        <tr><td><strong>{{ $line->productName }}</strong><br><small>{{ $line->sku }}</small></td><td>{{ $line->quantity }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->unitPriceMinor) }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->lineTotalMinor) }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="pos-receipt__totals">
                <div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->subtotalMinor) }}</dd></div>
                @if($receipt->discountMinor > 0)<div><dt>Discount</dt><dd>-{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->discountMinor) }}</dd></div>@endif
                <div><dt>Tax {{ $receipt->taxInclusive ? 'included' : '' }}</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->taxMinor) }}</dd></div>
                <div><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->totalMinor) }}</dd></div>
            </dl>

            <section class="pos-receipt__payments" aria-labelledby="receipt-payment-title">
                <h2 id="receipt-payment-title">Payment</h2>
                @foreach($receipt->payments as $payment)
                    <div><span>{{ $payment->methodLabel }}@if($payment->reference) &middot; {{ $payment->reference }}@endif</span><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($payment->amountMinor) }}</strong></div>
                    @if($payment->changeMinor > 0)<div><span>Cash change</span><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($payment->changeMinor) }}</strong></div>@endif
                @endforeach
            </section>

            <footer class="pos-receipt__footer">
                <strong>Thank you for your purchase.</strong>
                <p>{{ $institutionProfile->name }}@if($institutionProfile->primaryPhone) &middot; {{ $institutionProfile->primaryPhone }}@endif @if($institutionProfile->primaryEmail) &middot; {{ $institutionProfile->primaryEmail }}@endif</p>
            </footer>
        </article>

        <script type="application/json" data-pos-print-instruction>@json($printInstruction->toBrowserArray())</script>
    </div>
@endsection
