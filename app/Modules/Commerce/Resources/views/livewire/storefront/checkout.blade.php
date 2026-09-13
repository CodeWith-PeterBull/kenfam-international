<form class="commerce-checkout-layout" wire:submit="place" novalidate>
    <div class="commerce-checkout-form">
        @error('checkout')<div class="commerce-notice commerce-notice--error" role="alert">{{ $message }}</div>@enderror
        @foreach ($this->snapshot->issues as $issue)<div class="commerce-notice commerce-notice--warning" role="alert">{{ $issue }}</div>@endforeach

        <fieldset class="commerce-checkout-section">
            <legend><span>01</span>Contact details</legend>
            <div class="commerce-form-grid commerce-form-grid--two">
                <div class="commerce-field"><label for="checkout-first-name">First name</label><input id="checkout-first-name" type="text" wire:model.blur="form.firstName" autocomplete="given-name" required>@error('form.firstName')<small>{{ $message }}</small>@enderror</div>
                <div class="commerce-field"><label for="checkout-last-name">Last name</label><input id="checkout-last-name" type="text" wire:model.blur="form.lastName" autocomplete="family-name" required>@error('form.lastName')<small>{{ $message }}</small>@enderror</div>
                <div class="commerce-field"><label for="checkout-email">Email</label><input id="checkout-email" type="email" wire:model.blur="form.email" autocomplete="email" required>@error('form.email')<small>{{ $message }}</small>@enderror</div>
                <div class="commerce-field"><label for="checkout-phone">Phone</label><input id="checkout-phone" type="tel" wire:model.blur="form.phone" autocomplete="tel" required>@error('form.phone')<small>{{ $message }}</small>@enderror</div>
                <div class="commerce-field"><label for="checkout-company">Company <span>Optional</span></label><input id="checkout-company" type="text" wire:model.blur="form.company" autocomplete="organization">@error('form.company')<small>{{ $message }}</small>@enderror</div>
                <div class="commerce-field"><label for="checkout-tax-id">Tax identifier <span>Optional</span></label><input id="checkout-tax-id" type="text" wire:model.blur="form.taxIdentifier">@error('form.taxIdentifier')<small>{{ $message }}</small>@enderror</div>
            </div>
        </fieldset>

        <fieldset class="commerce-checkout-section">
            <legend><span>02</span>Fulfillment</legend>
            <div class="commerce-choice-grid">
                <label class="commerce-choice {{ $form->fulfillmentType === 'pickup' ? 'active' : '' }}"><input type="radio" wire:model.live="form.fulfillmentType" value="pickup"><span class="commerce-choice__icon" wire:ignore><i data-lucide="store" aria-hidden="true"></i></span><span class="commerce-choice__copy"><strong>Store pickup</strong><small>Collect from the store after confirmation.</small></span></label>
                <label class="commerce-choice {{ $form->fulfillmentType === 'delivery' ? 'active' : '' }}"><input type="radio" wire:model.live="form.fulfillmentType" value="delivery"><span class="commerce-choice__icon" wire:ignore><i data-lucide="truck" aria-hidden="true"></i></span><span class="commerce-choice__copy"><strong>Delivery</strong><small>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->deliveryFeeMinor) }} configured fee.</small></span></label>
            </div>
            @error('form.fulfillmentType')<small class="commerce-field-error">{{ $message }}</small>@enderror

            @if ($form->fulfillmentType === 'delivery')
                <div class="commerce-form-grid commerce-form-grid--two commerce-address-fields">
                    <div class="commerce-field commerce-field--wide"><label for="checkout-address-1">Address line 1</label><input id="checkout-address-1" type="text" wire:model.blur="form.addressLine1" autocomplete="address-line1" required>@error('form.addressLine1')<small>{{ $message }}</small>@enderror</div>
                    <div class="commerce-field commerce-field--wide"><label for="checkout-address-2">Address line 2 <span>Optional</span></label><input id="checkout-address-2" type="text" wire:model.blur="form.addressLine2" autocomplete="address-line2">@error('form.addressLine2')<small>{{ $message }}</small>@enderror</div>
                    <div class="commerce-field"><label for="checkout-city">City</label><input id="checkout-city" type="text" wire:model.blur="form.city" autocomplete="address-level2" required>@error('form.city')<small>{{ $message }}</small>@enderror</div>
                    <div class="commerce-field"><label for="checkout-region">Region or county <span>Optional</span></label><input id="checkout-region" type="text" wire:model.blur="form.region" autocomplete="address-level1">@error('form.region')<small>{{ $message }}</small>@enderror</div>
                    <div class="commerce-field"><label for="checkout-postal">Postal code <span>Optional</span></label><input id="checkout-postal" type="text" wire:model.blur="form.postalCode" autocomplete="postal-code">@error('form.postalCode')<small>{{ $message }}</small>@enderror</div>
                    <div class="commerce-field"><label for="checkout-country">Country code</label><input id="checkout-country" type="text" wire:model.blur="form.countryCode" maxlength="2" autocomplete="country" required>@error('form.countryCode')<small>{{ $message }}</small>@enderror</div>
                </div>
            @endif
        </fieldset>

        <fieldset class="commerce-checkout-section">
            <legend><span>03</span>Payment preference</legend>
            <div class="commerce-choice-grid commerce-choice-grid--three">
                @foreach ($this->paymentMethods as $method)
                    <label class="commerce-choice {{ $form->paymentMethod === $method->value ? 'active' : '' }}"><input type="radio" wire:model.live="form.paymentMethod" value="{{ $method->value }}"><span class="commerce-choice__icon" wire:ignore><i data-lucide="{{ $method === \App\Modules\Commerce\Orders\Enums\PaymentMethod::MobileMoney ? 'smartphone' : ($method === \App\Modules\Commerce\Orders\Enums\PaymentMethod::BankTransfer ? 'landmark' : 'banknote') }}" aria-hidden="true"></i></span><span class="commerce-choice__copy"><strong>{{ $method->label() }}</strong><small>Manual confirmation after placement.</small></span></label>
                @endforeach
            </div>
            @error('form.paymentMethod')<small class="commerce-field-error">{{ $message }}</small>@enderror
        </fieldset>

        <fieldset class="commerce-checkout-section">
            <legend><span>04</span>Order note</legend>
            <div class="commerce-field"><label for="checkout-note">Fulfillment note <span>Optional</span></label><textarea id="checkout-note" rows="4" wire:model.blur="form.customerNote" maxlength="1000"></textarea>@error('form.customerNote')<small>{{ $message }}</small>@enderror</div>
        </fieldset>

        <div class="commerce-checkout-status" wire:loading.delay wire:target.except="place" role="status" aria-live="polite">
            <span class="commerce-spinner" aria-hidden="true"></span>
            <span>Updating your order…</span>
        </div>
    </div>

    <aside class="commerce-order-summary commerce-checkout-summary" aria-labelledby="checkout-summary-title">
        <p class="commerce-eyebrow">Your order</p>
        <h2 id="checkout-summary-title">{{ $this->snapshot->itemCount }} {{ \Illuminate\Support\Str::plural('item', $this->snapshot->itemCount) }}</h2>
        <div class="commerce-checkout-summary__lines">
            @foreach ($this->snapshot->calculation->lines as $line)
                <div><span>{{ $line->product->name }} <small>x{{ $line->quantity }}</small></span><strong>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($line->totalMinor) }}</strong></div>
            @endforeach
        </div>
        <dl>
            <div><dt>Subtotal</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->subtotalMinor) }}</dd></div>
            <div><dt>Delivery</dt><dd>{{ $this->snapshot->calculation->deliveryFeeMinor > 0 ? \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->deliveryFeeMinor) : 'No fee' }}</dd></div>
            <div><dt>Tax {{ $this->snapshot->calculation->taxInclusive ? '(included)' : '' }}</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->taxMinor) }}</dd></div>
            <div class="commerce-order-summary__total"><dt>Total</dt><dd>{{ \App\Modules\Commerce\Support\MoneyFormatter::format($this->snapshot->calculation->totalMinor) }}</dd></div>
        </dl>
        <label class="commerce-check"><input type="checkbox" wire:model="form.acceptedTerms"><span>I confirm these order and contact details.</span></label>
        @error('form.acceptedTerms')<small class="commerce-field-error">{{ $message }}</small>@enderror
        <button class="commerce-button" type="submit" wire:loading.attr="disabled" wire:target="place" @disabled(! $this->snapshot->canCheckout())>
            <i data-lucide="lock-keyhole" wire:loading.remove wire:target="place" aria-hidden="true"></i>
            <span class="commerce-spinner" wire:loading wire:target="place" aria-hidden="true"></span>
            <span wire:loading.remove wire:target="place">Place order</span>
            <span wire:loading wire:target="place">Placing order…</span>
        </button>
        <p><i data-lucide="shield-check" aria-hidden="true"></i>Prices and stock are verified again when the order is placed.</p>
    </aside>
</form>
