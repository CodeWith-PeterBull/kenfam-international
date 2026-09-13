<div class="commerce-public-order-grid">
    <section class="commerce-public-order-panel" aria-labelledby="order-items-title">
        <div class="commerce-cart-section-heading"><div><p class="commerce-eyebrow">Products</p><h2 id="order-items-title">Order items</h2></div><span>{{ $order->items->sum('quantity') }} items</span></div>
        <div class="commerce-public-order-lines">
            @foreach ($order->items as $item)
                <article><div><p>{{ $item->sku }}</p><h3>{{ $item->product_name }}</h3><span>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($item->unit_price_minor) }} x {{ $item->quantity }}</span></div><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($item->line_total_minor) }}</strong></article>
            @endforeach
        </div>
    </section>

    <aside class="commerce-order-summary" aria-labelledby="public-order-summary-title">
        <p class="commerce-eyebrow">Summary</p><h2 id="public-order-summary-title">{{ $order->order_number }}</h2>
        <dl>
            <div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->subtotal_minor) }}</dd></div>
            @if ($order->discount_minor > 0)<div><dt>Discount</dt><dd>-{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->discount_minor) }}</dd></div>@endif
            <div><dt>Delivery</dt><dd>{{ $order->delivery_fee_minor > 0 ? \App\Modules\Commerce\Support\MoneyFormatter::format($order->delivery_fee_minor) : 'No fee' }}</dd></div>
            <div><dt>Tax {{ $order->tax_inclusive ? '(included)' : '' }}</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->tax_minor) }}</dd></div>
            <div class="commerce-order-summary__total"><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->total_minor) }}</dd></div>
        </dl>
        <div class="commerce-public-order-meta"><span><i data-lucide="package-check" aria-hidden="true"></i>{{ $order->fulfillment_type->label() }}</span><span><i data-lucide="wallet-cards" aria-hidden="true"></i>{{ $order->preferred_payment_method?->label() ?? 'Payment to be confirmed' }}</span><span><i data-lucide="badge-check" aria-hidden="true"></i>{{ $order->payment_status->label() }}</span></div>
    </aside>
</div>
