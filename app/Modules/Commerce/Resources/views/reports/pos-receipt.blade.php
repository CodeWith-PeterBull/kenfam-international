@extends($reportContext->orientation->layout())

@section('content')
    <section class="report-section">
        <div class="metrics">
            <div class="metric"><strong>{{ $receipt->itemQuantity() }}</strong>Items</div>
            <div class="metric"><strong>{{ $receipt->registerCode }}</strong>Register</div>
            <div class="metric"><strong>{{ $receipt->tenderCount() }}</strong>Tenders</div>
            <div class="metric"><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->totalMinor) }}</strong>Total</div>
        </div>
        <div class="filters"><strong>Cashier:</strong> {{ $receipt->cashierName }} | <strong>Customer:</strong> {{ $receipt->customerName }} | <strong>Sale:</strong> {{ $receipt->placedAt?->format('d M Y, H:i') }}</div>
    </section>

    <section class="report-section">
        <h2 class="section-title">Purchased items</h2>
        <table>
            <thead><tr><th style="width: 7%">#</th><th>Product</th><th style="width: 11%">Qty</th><th style="width: 22%">Unit price</th><th style="width: 22%">Total</th></tr></thead>
            <tbody>@foreach($receipt->lines as $line)<tr><td class="text-center">{{ $loop->iteration }}</td><td><strong>{{ $line->productName }}</strong><br>{{ $line->sku }}</td><td class="text-center">{{ $line->quantity }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->unitPriceMinor) }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->lineTotalMinor) }}</td></tr>@endforeach</tbody>
        </table>
    </section>

    <section class="report-section">
        <h2 class="section-title">Sale totals</h2>
        <table><tbody><tr><td><strong>Subtotal</strong></td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->subtotalMinor) }}</td></tr>@if($receipt->discountMinor > 0)<tr><td><strong>Discount</strong></td><td>-{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->discountMinor) }}</td></tr>@endif<tr><td><strong>Tax {{ $receipt->taxInclusive ? '(included)' : '' }}</strong></td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->taxMinor) }}</td></tr><tr><td><strong>Total</strong></td><td><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($receipt->totalMinor) }}</strong></td></tr></tbody></table>
    </section>

    <section class="report-section">
        <h2 class="section-title">Payment tenders</h2>
        <table><thead><tr><th>Method</th><th>Reference</th><th>Amount</th><th>Change</th></tr></thead><tbody>@foreach($receipt->payments as $payment)<tr><td>{{ $payment->methodLabel }}</td><td>{{ $payment->reference ?: 'Not applicable' }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($payment->amountMinor) }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($payment->changeMinor) }}</td></tr>@endforeach</tbody></table>
    </section>
@endsection
