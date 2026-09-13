@extends($reportContext->orientation->layout())

@section('content')
    <section class="report-section">
        <div class="metrics">
            <div class="metric"><strong>{{ $document->itemQuantity() }}</strong>Items</div>
            <div class="metric"><strong>{{ $document->statusLabel }}</strong>Order status</div>
            <div class="metric"><strong>{{ $document->paymentStatusLabel }}</strong>Payment</div>
            <div class="metric"><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->totalMinor) }}</strong>Total</div>
        </div>
        <div class="filters"><strong>Customer:</strong> {{ $document->customerName }} | <strong>Placed:</strong> {{ $document->placedAt?->format('d M Y, H:i') }} | <strong>Fulfillment:</strong> {{ $document->fulfillmentLabel }}</div>
    </section>

    <section class="report-section">
        <h2 class="section-title">Order items</h2>
        <table>
            <thead><tr><th style="width: 7%">#</th><th>Product</th><th style="width: 12%">Qty</th><th style="width: 20%">Unit price</th>@if ($orientation === 'landscape')<th style="width: 16%">Tax</th>@endif<th style="width: 20%">Total</th></tr></thead>
            <tbody>@foreach ($document->lines as $line)<tr><td class="text-center">{{ $loop->iteration }}</td><td><strong>{{ $line->productName }}</strong><br>{{ $line->sku }}</td><td class="text-center">{{ $line->quantity }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->unitPriceMinor) }}</td>@if ($orientation === 'landscape')<td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->taxMinor) }}</td>@endif<td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->lineTotalMinor) }}</td></tr>@endforeach</tbody>
        </table>
    </section>

    <section class="report-section">
        <table><tbody><tr><td><strong>Subtotal</strong></td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->subtotalMinor) }}</td></tr>@if ($document->discountMinor > 0)<tr><td><strong>Discount</strong></td><td>-{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->discountMinor) }}</td></tr>@endif<tr><td><strong>Delivery</strong></td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->deliveryFeeMinor) }}</td></tr><tr><td><strong>Tax {{ $document->taxInclusive ? '(included)' : '' }}</strong></td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->taxMinor) }}</td></tr><tr><td><strong>Order total</strong></td><td><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($document->totalMinor) }}</strong></td></tr></tbody></table>
    </section>
@endsection
