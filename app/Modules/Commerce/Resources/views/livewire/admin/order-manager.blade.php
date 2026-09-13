<div id="commerce-order-manager">
    @php($stats = [
        ['label' => 'All orders', 'value' => $this->statistics['total'], 'icon' => 'ti-receipt', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Awaiting review', 'value' => $this->statistics['pending'], 'icon' => 'ti-clock', 'color' => '#b7791f'],
        ['label' => 'In fulfillment', 'value' => $this->statistics['active'], 'icon' => 'ti-truck-delivery', 'color' => '#0f766e'],
        ['label' => 'Paid revenue', 'value' => \App\Modules\Commerce\Support\MoneyFormatter::format($this->statistics['revenue_minor']), 'icon' => 'ti-cash-banknote', 'color' => 'var(--aureon-accent)'],
    ])

    <section class="row" aria-label="Order statistics">
        @foreach ($stats as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3"><span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span><div><h3>{{ is_int($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</h3><p>{{ $stat['label'] }}</p></div></div>
                </article>
            </div>
        @endforeach
    </section>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    @endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel mb-4" aria-labelledby="order-filters-title">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-4 col-lg-6"><label id="order-filters-title" for="order-search" class="form-label">Search orders</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="order-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Order, customer, email, or phone"></div></div>
                <div class="col-xl-2 col-md-4"><label for="order-channel" class="form-label">Channel</label><select id="order-channel" class="form-select" wire:model.live="channel"><option value="">All channels</option>@foreach (\App\Modules\Commerce\Orders\Enums\OrderChannel::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-4"><label for="order-status" class="form-label">Status</label><select id="order-status" class="form-select" wire:model.live="status"><option value="">All statuses</option>@foreach (\App\Modules\Commerce\Orders\Enums\OrderStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-4"><label for="order-payment" class="form-label">Payment</label><select id="order-payment" class="form-select" wire:model.live="paymentStatus"><option value="">All states</option>@foreach (\App\Modules\Commerce\Orders\Enums\OrderPaymentStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></div>
                <div class="col-xl-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="clearFilters"><i class="ti ti-filter-off me-2"></i>Clear filters</button></div>
            </div>
        </div>
    </section>

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="order-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div><h3 id="order-table-title" class="card-title mb-1">Order register</h3><p class="aureon-muted fs-12 mb-0">{{ number_format($this->orders->total()) }} matching orders</p></div>
            <div class="d-flex align-items-center gap-2"><span class="aureon-activity-loading position-static" wire:loading.delay wire:target="search, channel, status, paymentStatus, perPage, orderPage" aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Updating</span></span><button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" title="Export the filtered orders as PDF"><span wire:loading.remove wire:target="exportPdf"><i class="ti ti-file-type-pdf me-1"></i>Export PDF</span><span wire:loading wire:target="exportPdf"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparing</span></button><select class="form-select form-select-sm aureon-per-page" wire:model.live="perPage" aria-label="Orders per page">@foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}">{{ $size }} / page</option>@endforeach</select></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 aureon-commerce-table">
                <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Channel</th><th scope="col">Total</th><th scope="col">Fulfillment</th><th scope="col">Payment</th><th scope="col">Placed</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->orders as $order)
                        @php($nextStatus = match ($order->status) {
                            \App\Modules\Commerce\Orders\Enums\OrderStatus::Pending => \App\Modules\Commerce\Orders\Enums\OrderStatus::Confirmed,
                            \App\Modules\Commerce\Orders\Enums\OrderStatus::Confirmed => \App\Modules\Commerce\Orders\Enums\OrderStatus::Processing,
                            \App\Modules\Commerce\Orders\Enums\OrderStatus::Processing => \App\Modules\Commerce\Orders\Enums\OrderStatus::Ready,
                            \App\Modules\Commerce\Orders\Enums\OrderStatus::Ready => \App\Modules\Commerce\Orders\Enums\OrderStatus::Completed,
                            default => null,
                        })
                        <tr wire:key="order-{{ $order->id }}">
                            <td><button type="button" class="btn btn-link p-0 fw-semibold text-start" wire:click="openDetails({{ $order->id }})">{{ $order->order_number }}</button><small class="d-block aureon-muted">{{ $order->items_count }} {{ \Illuminate\Support\Str::plural('line', $order->items_count) }}</small></td>
                            <td><span class="d-block fw-semibold">{{ $order->customer_display_name }}</span><small class="aureon-muted">{{ $order->customer_email ?: $order->customer_phone ?: 'Walk-in customer' }}</small></td>
                            <td><span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">{{ $order->channel->label() }}</span></td>
                            <td><span class="fw-semibold">{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->total_minor) }}</span></td>
                            <td><span class="badge aureon-commerce-badge aureon-commerce-badge--{{ $order->status->value }}">{{ $order->status->label() }}</span><small class="d-block aureon-muted mt-1">{{ $order->fulfillment_type->label() }}</small></td>
                            <td><span class="badge aureon-commerce-badge aureon-commerce-badge--payment-{{ $order->payment_status->value }}">{{ $order->payment_status->label() }}</span><small class="d-block aureon-muted mt-1">Due {{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->balance_due_minor) }}</small></td>
                            <td><span class="d-block">{{ $order->placed_at?->format('d M Y') ?? 'Not placed' }}</span><small class="aureon-muted">{{ $order->placed_at?->format('H:i') }}</small></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openDetails({{ $order->id }})" title="View order" aria-label="View {{ $order->order_number }}"><i class="ti ti-eye"></i></button>
                                @can('record', \App\Modules\Commerce\Orders\Models\Payment::class)
                                    @if ($order->channel === \App\Modules\Commerce\Orders\Enums\OrderChannel::Web && $order->balance_due_minor > 0 && $order->status !== \App\Modules\Commerce\Orders\Enums\OrderStatus::Cancelled)
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openPayment({{ $order->id }})" title="Record payment" aria-label="Record payment for {{ $order->order_number }}"><i class="ti ti-cash"></i></button>
                                    @endif
                                @endcan
                                @can('update', $order)
                                    @if ($nextStatus)
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="advanceOrder({{ $order->id }}, '{{ $nextStatus->value }}')" wire:confirm="Move {{ $order->order_number }} to {{ strtolower($nextStatus->label()) }}?">{{ $nextStatus->label() }} <i class="ti ti-arrow-right ms-1"></i></button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5"><span class="aureon-empty-state__icon"><i class="ti ti-receipt-off"></i></span><h4 class="fs-16 mb-1">No orders found</h4><p class="aureon-muted mb-0">Adjust the filters or wait for the first completed placement.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->orders->hasPages())<div class="card-footer">{{ $this->orders->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'details' && $this->selectedOrder)
        @php($order = $this->selectedOrder)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="order-details-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header"><div><h3 id="order-details-title" class="modal-title fs-18">{{ $order->order_number }}</h3><p class="aureon-muted fs-12 mb-0">{{ $order->channel->label() }} order placed {{ $order->placed_at?->format('d M Y, H:i') }}</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close order details"></button></div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <section class="aureon-form-section"><h4>Order items</h4><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th class="text-center">Qty</th><th>Unit price</th><th>Total</th></tr></thead><tbody>@foreach ($order->items as $item)<tr><td><span class="d-block fw-semibold">{{ $item->product_name }}</span><small class="aureon-muted">{{ $item->sku }}</small></td><td class="text-center">{{ $item->quantity }}</td><td>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($item->unit_price_minor) }}</td><td class="fw-semibold">{{ \App\Modules\Commerce\Support\MoneyFormatter::format($item->line_total_minor) }}</td></tr>@endforeach</tbody></table></div></section>
                                <section class="aureon-form-section"><h4>Customer snapshot</h4><div class="row g-3"><div class="col-md-6"><span class="aureon-muted fs-12">Name</span><p class="mb-0 fw-semibold">{{ $order->customer_display_name }}</p></div><div class="col-md-6"><span class="aureon-muted fs-12">Contact</span><p class="mb-0">{{ $order->customer_email ?: 'No email' }}<br>{{ $order->customer_phone ?: 'No phone' }}</p></div><div class="col-12"><span class="aureon-muted fs-12">Fulfillment address</span><p class="mb-0">{{ collect([$order->address_line_1, $order->address_line_2, $order->city, $order->region, $order->postal_code, $order->country_code])->filter()->implode(', ') ?: 'Store pickup' }}</p></div>@if ($order->customer_note)<div class="col-12"><span class="aureon-muted fs-12">Customer note</span><p class="mb-0">{{ $order->customer_note }}</p></div>@endif</div></section>
                            </div>
                            <div class="col-lg-4">
                                <section class="aureon-form-section"><h4>Totals</h4><dl class="aureon-order-totals"><div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->subtotal_minor) }}</dd></div><div><dt>Delivery</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->delivery_fee_minor) }}</dd></div><div><dt>Tax</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->tax_minor) }}</dd></div><div class="fw-bold"><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->total_minor) }}</dd></div><div><dt>Paid</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->paid_minor) }}</dd></div><div><dt>Balance</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->balance_due_minor) }}</dd></div></dl></section>
                                <section class="aureon-form-section"><h4>Documents</h4><div class="d-grid gap-2"><a class="btn btn-outline-secondary" href="{{ route('commerce.admin.orders.document', ['order' => $order, 'orientation' => \App\Enums\ReportOrientation::Portrait]) }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-2"></i>Portrait PDF</a><a class="btn btn-outline-secondary" href="{{ route('commerce.admin.orders.document', ['order' => $order, 'orientation' => \App\Enums\ReportOrientation::Landscape]) }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-2"></i>Landscape PDF</a></div></section>
                                @can('cancel', $order)
                                    @if ($order->channel === \App\Modules\Commerce\Orders\Enums\OrderChannel::Web && $order->payment_status === \App\Modules\Commerce\Orders\Enums\OrderPaymentStatus::Unpaid && in_array($order->status, [\App\Modules\Commerce\Orders\Enums\OrderStatus::Pending, \App\Modules\Commerce\Orders\Enums\OrderStatus::Confirmed, \App\Modules\Commerce\Orders\Enums\OrderStatus::Processing, \App\Modules\Commerce\Orders\Enums\OrderStatus::Ready], true))
                                        <button type="button" class="btn btn-outline-danger w-100" wire:click="openCancellation({{ $order->id }})"><i class="ti ti-ban me-2"></i>Cancel order</button>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Close</button></div>
                </div>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close order details"></button>
    @endif

    @if ($dialog === 'payment' && $this->selectedOrder)
        @php($order = $this->selectedOrder)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="payment-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="recordPayment">
                    <div class="modal-header"><div><h3 id="payment-form-title" class="modal-title fs-18">Record payment</h3><p class="aureon-muted fs-12 mb-0">{{ $order->order_number }} has {{ \App\Modules\Commerce\Support\MoneyFormatter::format($order->balance_due_minor) }} outstanding</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close payment form"></button></div>
                    <div class="modal-body">@error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror<div class="mb-3"><label for="payment-method" class="form-label">Method</label><select id="payment-method" class="form-select @error('paymentForm.method') is-invalid @enderror" wire:model="paymentForm.method">@foreach (\App\Modules\Commerce\Orders\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select>@error('paymentForm.method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="mb-3"><label for="payment-amount" class="form-label">Amount ({{ config('commerce.currency.code') }})</label><input id="payment-amount" type="number" min="0.01" step="0.01" class="form-control @error('paymentForm.amount') is-invalid @enderror" wire:model="paymentForm.amount">@error('paymentForm.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div><label for="payment-reference" class="form-label">Reference</label><input id="payment-reference" type="text" class="form-control @error('paymentForm.reference') is-invalid @enderror" wire:model="paymentForm.reference" placeholder="Receipt, transaction, or bank reference">@error('paymentForm.reference')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordPayment"><i class="ti ti-cash me-2"></i><span wire:loading.remove wire:target="recordPayment">Confirm payment</span><span wire:loading wire:target="recordPayment">Recording...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close payment form"></button>
    @endif

    @if ($dialog === 'cancel' && $this->selectedOrder)
        @php($order = $this->selectedOrder)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="cancel-order-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="cancel">
                    <div class="modal-header"><div><h3 id="cancel-order-title" class="modal-title fs-18">Cancel {{ $order->order_number }}</h3><p class="aureon-muted fs-12 mb-0">Committed stock will be restored exactly once.</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close cancellation form"></button></div>
                    <div class="modal-body">@error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror<label for="cancellation-reason" class="form-label">Customer-facing reason</label><textarea id="cancellation-reason" rows="4" maxlength="255" class="form-control @error('cancellationReason') is-invalid @enderror" wire:model="cancellationReason"></textarea>@error('cancellationReason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Keep order</button><button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="cancel"><i class="ti ti-ban me-2"></i><span wire:loading.remove wire:target="cancel">Cancel and restore stock</span><span wire:loading wire:target="cancel">Cancelling...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close cancellation form"></button>
    @endif
</div>
