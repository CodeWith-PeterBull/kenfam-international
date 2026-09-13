<div class="commerce-cart-workspace">
    <div class="commerce-cart-announcer" aria-live="polite">
        @if (session('warning'))<p class="commerce-notice commerce-notice--warning">{{ session('warning') }}</p>@endif
        @error('cart')<p class="commerce-notice commerce-notice--error">{{ $message }}</p>@enderror
        @if ($feedback !== '')<p class="commerce-notice commerce-notice--success">{{ $feedback }}</p>@endif
        @foreach ($this->snapshot->issues as $issue)<p class="commerce-notice commerce-notice--warning">{{ $issue }}</p>@endforeach
    </div>

    @if ($this->snapshot->isEmpty())
        <section class="commerce-empty-state" aria-labelledby="empty-cart-title">
            <i data-lucide="shopping-bag" aria-hidden="true"></i>
            <p class="commerce-eyebrow">Your cart</p>
            <h2 id="empty-cart-title">Nothing here yet.</h2>
            <p>Browse the catalog and add products when you are ready.</p>
            <a class="commerce-button" href="{{ route('commerce.storefront.catalog.index') }}">Browse products <i data-lucide="arrow-right" aria-hidden="true"></i></a>
        </section>
    @else
        <div class="commerce-cart-layout">
            <section class="commerce-cart-lines" aria-labelledby="cart-lines-title">
                <div class="commerce-cart-section-heading">
                    <div><p class="commerce-eyebrow">Selected products</p><h2 id="cart-lines-title">{{ $this->snapshot->itemCount }} {{ \Illuminate\Support\Str::plural('item', $this->snapshot->itemCount) }}</h2></div>
                    <button class="commerce-text-button" type="button" wire:click="clear" wire:confirm="Clear every product from your cart?">Clear cart</button>
                </div>

                @foreach ($this->snapshot->calculation->lines as $line)
                    @php($image = $line->product->getFirstMediaUrl('product_gallery') ?: asset('aureon/assets/brand/logo-icon.png'))
                    <article class="commerce-cart-line" wire:key="cart-line-{{ $line->product->id }}">
                        <a class="commerce-cart-line__image" href="{{ route('commerce.storefront.products.show', ['product' => $line->product->slug]) }}">
                            <img src="{{ $image }}" alt="{{ $line->product->name }}" width="180" height="180">
                        </a>
                        <div class="commerce-cart-line__details">
                            <p>{{ $line->product->category?->name ?? 'General' }}</p>
                            <h3><a href="{{ route('commerce.storefront.products.show', ['product' => $line->product->slug]) }}">{{ $line->product->name }}</a></h3>
                            <span>SKU {{ $line->product->sku }}</span>
                            <button class="commerce-text-button" type="button" wire:click="remove({{ $line->product->id }})">Remove</button>
                        </div>
                        <div class="commerce-cart-line__quantity">
                            <span>Quantity</span>
                            <div class="commerce-quantity-control" role="group" aria-label="Quantity for {{ $line->product->name }}">
                                <button type="button" wire:click="decrement({{ $line->product->id }})" aria-label="Decrease {{ $line->product->name }} quantity"><i data-lucide="minus" aria-hidden="true"></i></button>
                                <output aria-live="polite">{{ $line->quantity }}</output>
                                <button type="button" wire:click="increment({{ $line->product->id }})" aria-label="Increase {{ $line->product->name }} quantity"><i data-lucide="plus" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="commerce-cart-line__price">
                            <span>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->unitPriceMinor) }} each</span>
                            <strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->totalMinor) }}</strong>
                        </div>
                    </article>
                @endforeach
            </section>

            <aside class="commerce-order-summary" aria-labelledby="cart-summary-title">
                <p class="commerce-eyebrow">Order summary</p>
                <h2 id="cart-summary-title">Current totals</h2>
                <dl>
                    <div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->subtotalMinor) }}</dd></div>
                    <div><dt>Tax {{ $this->snapshot->calculation->taxInclusive ? '(included)' : '' }}</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->taxMinor) }}</dd></div>
                    <div class="commerce-order-summary__total"><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->totalMinor) }}</dd></div>
                </dl>
                @if ($this->snapshot->canCheckout())
                    <a class="commerce-button" href="{{ route('commerce.storefront.checkout.index') }}">Continue to checkout <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                @else
                    <button class="commerce-button" type="button" disabled>Review cart availability</button>
                @endif
                <a class="commerce-button commerce-button--secondary" href="{{ route('commerce.storefront.catalog.index') }}">Continue shopping</a>
                <p>Delivery options and any configured fee are calculated at checkout.</p>
            </aside>
        </div>
    @endif
</div>
