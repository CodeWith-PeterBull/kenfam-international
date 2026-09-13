<div class="pos-workspace" data-pos-terminal>
    @if (session('pos-success'))
        <div class="pos-alert pos-alert--success" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span>{{ session('pos-success') }}</span></div>
    @endif
    @error('terminal')<div class="pos-alert pos-alert--danger" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>{{ $message }}</span></div>@enderror

    @if (! $this->activeTill)
        <section class="pos-empty-state" aria-labelledby="no-till-title">
            <span><i class="ti ti-lock" aria-hidden="true"></i></span>
            <p class="pos-eyebrow">Terminal unavailable</p>
            <h1 id="no-till-title">Open a till before taking payments</h1>
            <p>Your account needs one active till session. A till manager can assign an available register and opening float.</p>
            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS)
                <a class="pos-button" href="{{ route('commerce.pos.admin.tills.index') }}"><i class="ti ti-lock-open" aria-hidden="true"></i>Open till session</a>
            @endcan
        </section>
    @else
        <div class="pos-session-bar" aria-label="Current till session">
            <div><span class="pos-session-bar__signal"></span><div><strong>{{ $this->activeTill->register->name }}</strong><small>{{ $this->activeTill->register->code }} · Opened {{ $this->activeTill->opened_at?->format('d M Y, H:i') }}@if ($this->activeTill->opened_at?->lt(now()->subDay()))<span class="text-warning ms-1">· {{ $this->activeTill->opened_at->diffForHumans() }}</span>@endif</small></div></div>
            <dl><div><dt>Opening float</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->activeTill->opening_float_minor) }}</dd></div><div><dt>Held sales</dt><dd>{{ $this->heldOrders->count() }}</dd></div></dl>
        </div>

        <div class="pos-terminal-grid">
            <section class="pos-catalog-panel" aria-labelledby="product-browser-title">
                <div class="pos-section-heading">
                    <div><p class="pos-eyebrow">Product browser</p><h1 id="product-browser-title">Build the sale</h1></div>
                    <span>{{ $this->products->count() }} shown</span>
                </div>

                <form class="pos-search" wire:submit="lookup" role="search">
                    <i class="ti ti-scan" aria-hidden="true"></i>
                    <label class="visually-hidden" for="pos-product-search">Barcode, SKU, or product name</label>
                    <input id="pos-product-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Scan barcode or search by SKU and name" autocomplete="off" autofocus>
                    <button type="submit" aria-label="Run product lookup" title="Search"><i class="ti ti-arrow-right"></i></button>
                </form>
                @error('lookup')<p class="pos-field-error" role="alert">{{ $message }}</p>@enderror
                @error('cart')<p class="pos-field-error" role="alert">{{ $message }}</p>@enderror

                <div class="pos-product-grid" wire:loading.class="is-loading" wire:target="search,lookup,addProduct">
                    @forelse ($this->products as $product)
                        @php
                            $available = $product->track_stock ? (int) ($product->stock?->on_hand ?? 0) : null;
                            $image = $product->getFirstMediaUrl('product_gallery') ?: asset('aureon/assets/brand/logo-icon.png');
                        @endphp
                        <button class="pos-product {{ $product->isInStock() ? '' : 'is-disabled' }}" type="button" wire:click="addProduct({{ $product->id }})" wire:key="pos-product-{{ $product->id }}" @disabled(! $product->isInStock())>
                            <span class="pos-product__image"><img src="{{ $image }}" alt="" width="240" height="240" loading="lazy"></span>
                            <span class="pos-product__copy"><small>{{ $product->sku }}</small><strong>{{ $product->name }}</strong><span>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($product->effective_price_minor) }}</span></span>
                            <span class="pos-product__stock {{ $product->isInStock() ? '' : 'is-empty' }}">{{ $available === null ? 'Available' : ($available > 0 ? $available.' in stock' : 'Out of stock') }}</span>
                        </button>
                    @empty
                        <div class="pos-no-results"><i class="ti ti-package-off" aria-hidden="true"></i><strong>No products found</strong><span>Try a different barcode, SKU, or product name.</span></div>
                    @endforelse
                </div>

                <details class="pos-held-sales" @if($this->heldOrders->isNotEmpty()) open @endif>
                    <summary><span><i class="ti ti-clock-pause" aria-hidden="true"></i>Held sales</span><strong>{{ $this->heldOrders->count() }}</strong></summary>
                    <div class="pos-held-sales__list">
                        @forelse ($this->heldOrders as $held)
                            <article wire:key="held-order-{{ $held->id }}">
                                <div><strong>{{ $held->customer_display_name }}</strong><span>{{ $held->items_count }} items · {{ \App\Modules\Commerce\Support\MoneyFormatter::format($held->total_minor) }}</span><small>{{ $held->created_at?->format('d M, H:i') }}</small></div>
                                <div><button type="button" wire:click="resume({{ $held->id }})"><i class="ti ti-player-play" aria-hidden="true"></i>Resume</button><button class="is-danger" type="button" wire:click="discard({{ $held->id }})" wire:confirm="Discard this held sale?"><i class="ti ti-trash" aria-hidden="true"></i>Discard</button></div>
                            </article>
                        @empty
                            <p>No sales are currently held on this till.</p>
                        @endforelse
                    </div>
                </details>

                @if (config('commerce.pos.sales_history.terminal_enabled', true))
                    <livewire:commerce.pos.cashier-sales-history surface="terminal" key="terminal-cashier-sales-history" />
                @endif
            </section>

            <aside class="pos-sale-panel" aria-labelledby="current-sale-title">
                <div class="pos-sale-panel__heading">
                    <div><p class="pos-eyebrow">{{ $resumingOrderId ? 'Resumed hold' : 'Current sale' }}</p><h2 id="current-sale-title">{{ $this->snapshot->itemCount() }} items</h2></div>
                    @if ($this->snapshot->itemCount() > 0)<button class="pos-icon-button" type="button" wire:click="clearSale" wire:confirm="Clear the current sale?" aria-label="Clear current sale" title="Clear sale"><i class="ti ti-trash"></i></button>@endif
                </div>

                <div class="pos-cart-lines">
                    @forelse ($this->snapshot->calculation?->lines ?? [] as $line)
                        <article class="pos-cart-line" wire:key="pos-cart-line-{{ $line->product->id }}">
                            <div><span>{{ $line->product->sku }}</span><strong>{{ $line->product->name }}</strong><small>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->unitPriceMinor) }} each</small></div>
                            <div class="pos-cart-line__controls"><button type="button" wire:click="decrease({{ $line->product->id }})" aria-label="Decrease {{ $line->product->name }} quantity"><i class="ti ti-minus"></i></button><output aria-label="Quantity">{{ $line->quantity }}</output><button type="button" wire:click="increase({{ $line->product->id }})" aria-label="Increase {{ $line->product->name }} quantity"><i class="ti ti-plus"></i></button></div>
                            <strong class="pos-cart-line__total">{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->totalMinor) }}</strong>
                            <button class="pos-cart-line__remove" type="button" wire:click="remove({{ $line->product->id }})" aria-label="Remove {{ $line->product->name }}" title="Remove"><i class="ti ti-x"></i></button>
                        </article>
                    @empty
                        <div class="pos-cart-empty"><i class="ti ti-shopping-cart" aria-hidden="true"></i><strong>The sale is empty</strong><span>Select a product or scan its barcode.</span></div>
                    @endforelse
                </div>

                @foreach ($this->snapshot->issues as $issue)<p class="pos-field-error" role="alert">{{ $issue }}</p>@endforeach

                <div class="pos-sale-fields">
                    <details wire:ignore.self>
                        <summary><span><i class="ti ti-user" aria-hidden="true"></i>Customer</span><strong>{{ $this->selectedCustomerLabel ?? 'Walk-in' }}</strong></summary>
                        <div class="pos-detail-body">
                            <label for="pos-customer-search">Find customer</label><input id="pos-customer-search" type="search" wire:model.live.debounce.250ms="customerSearch" placeholder="Name, email, or phone">
                            <label for="pos-customer">Selected customer</label><select id="pos-customer" wire:model.live="customerId"><option value="">Walk-in customer</option>@foreach($this->customers as $customer)<option value="{{ $customer->id }}">{{ $customer->display_name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select>
                        </div>
                    </details>
                    <details wire:ignore.self>
                        <summary><span><i class="ti ti-discount" aria-hidden="true"></i>Discount and note</span><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation?->discountMinor ?? 0) }}</strong></summary>
                        <div class="pos-detail-body pos-detail-body--columns"><div><label for="pos-discount">Fixed discount</label><input id="pos-discount" type="text" inputmode="decimal" wire:model.live.debounce.300ms="discount"></div><div><label for="pos-discount-reason">Discount reason</label><input id="pos-discount-reason" type="text" wire:model.live.debounce.300ms="discountReason" maxlength="500"></div><div class="is-wide"><label for="pos-hold-note">Internal sale note</label><textarea id="pos-hold-note" rows="2" wire:model="holdNote" maxlength="2000"></textarea></div></div>
                        @error('discountReason')<p class="pos-field-error">{{ $message }}</p>@enderror
                    </details>
                </div>

                <dl class="pos-totals">
                    <div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation?->subtotalMinor ?? 0) }}</dd></div>
                    @if(($this->snapshot->calculation?->discountMinor ?? 0) > 0)<div><dt>Discount</dt><dd>-{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->discountMinor) }}</dd></div>@endif
                    <div><dt>Tax {{ ($this->snapshot->calculation?->taxInclusive ?? true) ? 'included' : '' }}</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation?->taxMinor ?? 0) }}</dd></div>
                    <div class="pos-totals__grand"><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation?->totalMinor ?? 0) }}</dd></div>
                </dl>

                <section class="pos-payments" aria-labelledby="payment-title">
                    <div class="pos-payments__heading"><h3 id="payment-title">Payment</h3><button type="button" wire:click="addTender" @disabled(count($tenders) >= max(1, (int) config('commerce.pos.max_tenders', 4)))><i class="ti ti-plus" aria-hidden="true"></i>Split tender</button></div>
                    @foreach($tenders as $index => $tender)
                        <div class="pos-tender" wire:key="pos-tender-{{ $index }}">
                            <div><label for="tender-method-{{ $index }}">Method</label><select id="tender-method-{{ $index }}" wire:model.live="tenders.{{ $index }}.method">@foreach($this->paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></div>
                            <div><label for="tender-amount-{{ $index }}">Amount</label><div class="pos-money-input"><input id="tender-amount-{{ $index }}" type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.amount"><button type="button" wire:click="applyRemaining({{ $index }})" title="Apply remaining balance">Due</button></div></div>
                            @if(($tender['method'] ?? '') === \App\Modules\Commerce\Orders\Enums\PaymentMethod::Cash->value)
                                <div><label for="tendered-{{ $index }}">Cash received</label><input id="tendered-{{ $index }}" type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.tendered"></div>
                            @else
                                <div><label for="tender-reference-{{ $index }}">Reference</label><input id="tender-reference-{{ $index }}" type="text" wire:model="tenders.{{ $index }}.reference" maxlength="120"></div>
                            @endif
                            @if(count($tenders) > 1)<button class="pos-tender__remove" type="button" wire:click="removeTender({{ $index }})" aria-label="Remove payment row" title="Remove payment"><i class="ti ti-x"></i></button>@endif
                            @error("tenders.$index.amount")<p class="pos-field-error">{{ $message }}</p>@enderror
                            @error("tenders.$index.reference")<p class="pos-field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @error('payments')<p class="pos-field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="pos-payment-summary"><span>Applied <strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->tenderTotalMinor()) }}</strong></span><span>Change <strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->cashChangeMinor()) }}</strong></span></div>
                </section>

                <div class="pos-sale-actions">
                    <button class="pos-button pos-button--secondary" type="button" wire:click="hold" wire:loading.attr="disabled" wire:target="hold,checkout" @disabled(! $this->snapshot->canTransact())><i class="ti ti-clock-pause" aria-hidden="true"></i>Hold sale</button>
                    <button class="pos-button" type="button" wire:click="checkout" wire:loading.attr="disabled" wire:target="hold,checkout" @disabled(! $this->snapshot->canTransact())><i class="ti ti-credit-card-pay" aria-hidden="true"></i><span wire:loading.remove wire:target="checkout">Complete sale</span><span wire:loading wire:target="checkout">Processing...</span></button>
                </div>
            </aside>
        </div>
    @endif
</div>
