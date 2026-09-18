@use('App\Modules\TravelTours\Bookings\Enums\ParticipantType')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $tour = $hold->departure->tour;
    $departure = $hold->departure;
    $snapshot = $hold->quote_snapshot ?? [];
    $lines = $snapshot['lines'] ?? [];
    $currency = $hold->currency;
    $localStart = $departure->starts_at->timezone($departure->timezone);
    $localEnd = $departure->ends_at->timezone($departure->timezone);
    $active = $this->holdIsActive();
    $mix = array_filter([
        $hold->adult_count.' '.Str::plural('adult', $hold->adult_count),
        $hold->child_count > 0 ? $hold->child_count.' '.Str::plural('child', $hold->child_count) : null,
        $hold->infant_count > 0 ? $hold->infant_count.' '.Str::plural('infant', $hold->infant_count) : null,
    ]);
    $depositMinor = (int) ($snapshot['deposit_minor'] ?? 0);
    $totalMinor = (int) ($snapshot['total_minor'] ?? $hold->quoted_total_minor);
@endphp
<div
    class="travel-checkout"
    x-data="{
        expiresAt: {{ $hold->expires_at->getTimestampMs() }},
        expired: {{ $active ? 'false' : 'true' }},
        label: '',
        tick() {
            const remaining = Math.max(0, Math.floor((this.expiresAt - Date.now()) / 1000));
            this.expired = this.expired || remaining === 0;
            if (this.expired) {
                this.label = 'Your held places have been released. Return to the tour page to start again.';
                return;
            }
            const minutes = Math.floor(remaining / 60);
            const seconds = String(remaining % 60).padStart(2, '0');
            this.label = `Places held for ${minutes}:${seconds}`;
        },
        init() {
            this.tick();
            const timer = setInterval(() => { this.tick(); if (this.expired) clearInterval(timer); }, 1000);
        }
    }"
>
    <div class="travel-checkout__main">
        <form class="travel-checkout__form" wire:submit="place" novalidate aria-describedby="checkout-hold-status">
            <section class="travel-checkout__block" aria-labelledby="checkout-customer-title">
                <h2 id="checkout-customer-title">Your details</h2>
                <p class="travel-checkout__hint">We use these details to confirm the booking and to send your private booking link.</p>
                <div class="travel-checkout__grid">
                    <label class="travel-field" for="checkout-first-name">
                        <span>First name</span>
                        <input id="checkout-first-name" type="text" autocomplete="given-name" maxlength="80" wire:model.blur="form.firstName" @if ($errors->has('form.firstName')) aria-invalid="true" aria-describedby="checkout-first-name-error" @endif>
                        @error('form.firstName')<small class="travel-selector__error" id="checkout-first-name-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                    <label class="travel-field" for="checkout-last-name">
                        <span>Last name</span>
                        <input id="checkout-last-name" type="text" autocomplete="family-name" maxlength="80" wire:model.blur="form.lastName" @if ($errors->has('form.lastName')) aria-invalid="true" aria-describedby="checkout-last-name-error" @endif>
                        @error('form.lastName')<small class="travel-selector__error" id="checkout-last-name-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                    <label class="travel-field" for="checkout-email">
                        <span>Email</span>
                        <input id="checkout-email" type="email" autocomplete="email" inputmode="email" maxlength="160" wire:model.blur="form.email" @if ($errors->has('form.email')) aria-invalid="true" aria-describedby="checkout-email-error" @endif>
                        @error('form.email')<small class="travel-selector__error" id="checkout-email-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                    <label class="travel-field" for="checkout-phone">
                        <span>Phone or WhatsApp</span>
                        <input id="checkout-phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="40" wire:model.blur="form.phone" @if ($errors->has('form.phone')) aria-invalid="true" aria-describedby="checkout-phone-error" @endif>
                        @error('form.phone')<small class="travel-selector__error" id="checkout-phone-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                </div>
            </section>

            <section class="travel-checkout__block" aria-labelledby="checkout-travellers-title">
                <h2 id="checkout-travellers-title">Travellers</h2>
                <p class="travel-checkout__hint">Names as they appear on travel documents. Dates of birth confirm child and infant fares.</p>
                @foreach ($form->participants as $index => $participant)
                    @php
                        $type = ParticipantType::from($participant['type']);
                        $usesCustomer = $index === 0 && $form->leadTravels;
                        $minor = $type !== ParticipantType::Adult;
                    @endphp
                    <fieldset class="travel-checkout__traveller" wire:key="traveller-{{ $index }}">
                        <legend>Traveller {{ $index + 1 }} <small>{{ $type->label() }}</small></legend>
                        @if ($index === 0)
                            <label class="travel-checkbox" for="checkout-lead-travels">
                                <input id="checkout-lead-travels" type="checkbox" wire:model.live="form.leadTravels">
                                <span>I am travelling; use my details for traveller 1.</span>
                            </label>
                        @endif
                        <div class="travel-checkout__grid">
                            @unless ($usesCustomer)
                                <label class="travel-field" for="checkout-participant-{{ $index }}-first-name">
                                    <span>First name</span>
                                    <input id="checkout-participant-{{ $index }}-first-name" type="text" autocomplete="off" maxlength="80" wire:model.blur="form.participants.{{ $index }}.first_name" @if ($errors->has("form.participants.{$index}.first_name")) aria-invalid="true" aria-describedby="checkout-participant-{{ $index }}-first-name-error" @endif>
                                    @error("form.participants.{$index}.first_name")<small class="travel-selector__error" id="checkout-participant-{{ $index }}-first-name-error" role="alert">{{ $message }}</small>@enderror
                                </label>
                                <label class="travel-field" for="checkout-participant-{{ $index }}-last-name">
                                    <span>Last name</span>
                                    <input id="checkout-participant-{{ $index }}-last-name" type="text" autocomplete="off" maxlength="80" wire:model.blur="form.participants.{{ $index }}.last_name" @if ($errors->has("form.participants.{$index}.last_name")) aria-invalid="true" aria-describedby="checkout-participant-{{ $index }}-last-name-error" @endif>
                                    @error("form.participants.{$index}.last_name")<small class="travel-selector__error" id="checkout-participant-{{ $index }}-last-name-error" role="alert">{{ $message }}</small>@enderror
                                </label>
                            @endunless
                            <label class="travel-field" for="checkout-participant-{{ $index }}-dob">
                                <span>Date of birth @unless ($minor)<small>(optional)</small>@endunless</span>
                                <input id="checkout-participant-{{ $index }}-dob" type="date" max="{{ now()->subDay()->toDateString() }}" wire:model.blur="form.participants.{{ $index }}.date_of_birth" @if ($errors->has("form.participants.{$index}.date_of_birth")) aria-invalid="true" aria-describedby="checkout-participant-{{ $index }}-dob-error" @endif>
                                @error("form.participants.{$index}.date_of_birth")<small class="travel-selector__error" id="checkout-participant-{{ $index }}-dob-error" role="alert">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    </fieldset>
                @endforeach
            </section>

            <section class="travel-checkout__block" aria-labelledby="checkout-payment-title">
                <h2 id="checkout-payment-title">How would you like to pay?</h2>
                <p class="travel-checkout__hint">
                    No payment is taken online. Our travel desk will contact you to settle
                    @if ($depositMinor > 0 && $depositMinor < $totalMinor)
                        the deposit of <strong>{{ MoneyFormatter::format($depositMinor, $currency) }}</strong>
                    @else
                        <strong>{{ MoneyFormatter::format($totalMinor, $currency) }}</strong>
                    @endif
                    using the option you choose, and confirms your places once it is received.
                </p>
                <fieldset class="travel-checkout__methods">
                    <legend class="visually-hidden">Preferred payment method</legend>
                    @foreach ($methods as $method)
                        <label class="travel-checkout__method" for="checkout-method-{{ $method->value }}">
                            <input id="checkout-method-{{ $method->value }}" type="radio" name="preferredMethod" value="{{ $method->value }}" wire:model="form.preferredMethod">
                            <span>{{ $method->label() }}</span>
                        </label>
                    @endforeach
                </fieldset>
                @error('form.preferredMethod')<small class="travel-selector__error travel-selector__error--block" role="alert">{{ $message }}</small>@enderror
                <label class="travel-field" for="checkout-special-requests">
                    <span>Special requests <small>(optional)</small></span>
                    <textarea id="checkout-special-requests" rows="3" maxlength="1000" wire:model.blur="form.specialRequests" @if ($errors->has('form.specialRequests')) aria-invalid="true" @endif></textarea>
                    @error('form.specialRequests')<small class="travel-selector__error" role="alert">{{ $message }}</small>@enderror
                </label>
            </section>

            <section class="travel-checkout__block" aria-labelledby="checkout-terms-title">
                <h2 id="checkout-terms-title">Booking terms</h2>
                @if ($tour->cancellation_summary)
                    <p class="travel-checkout__hint">{{ $tour->cancellation_summary }}</p>
                @endif
                @if ($tour->terms)
                    <details class="travel-checkout__terms">
                        <summary>Read the full booking terms</summary>
                        <div class="travel-copy">{!! nl2br(e($tour->terms)) !!}</div>
                    </details>
                @endif
                <label class="travel-checkbox" for="checkout-accept-terms">
                    <input id="checkout-accept-terms" type="checkbox" wire:model="form.acceptTerms" @if ($errors->has('form.acceptTerms')) aria-invalid="true" aria-describedby="checkout-accept-terms-error" @endif>
                    <span>I accept the booking terms and cancellation policy{{ $tour->policy_version ? ' (version '.$tour->policy_version.')' : '' }}.</span>
                </label>
                @error('form.acceptTerms')<small class="travel-selector__error travel-selector__error--block" id="checkout-accept-terms-error" role="alert">{{ $message }}</small>@enderror
            </section>

            @error('checkout')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror
            @if ($errors->any() && ! $errors->has('checkout'))
                <div class="alert alert-danger" role="alert">Please review the highlighted details above.</div>
            @endif

            <button type="submit" class="travel-button travel-button--wide travel-checkout__submit" wire:loading.attr="disabled" x-bind:disabled="expired" @disabled(! $active)>
                <span wire:loading.remove wire:target="place">Confirm booking request</span>
                <span wire:loading wire:target="place">Placing your booking&hellip;</span>
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </button>
            <p class="travel-checkout__hint">You will receive a private link to track this booking. Our team confirms the booking once the payment you choose is recorded.</p>
        </form>
    </div>

    <aside class="travel-booking-panel travel-checkout__summary" aria-labelledby="checkout-summary-title">
        <p class="travel-eyebrow">Your selection</p>
        <h2 id="checkout-summary-title">{{ $tour->name }}</h2>
        <p class="travel-checkout__timer" id="checkout-hold-status" role="status" aria-live="polite" x-text="label" x-bind:class="{ 'is-expired': expired }">
            @if ($active)Places held until {{ $hold->expires_at->timezone($departure->timezone)->format('H:i') }}.@else Your held places have been released.@endif
        </p>
        <dl class="travel-booking-summary">
            <div><dt>Travel dates</dt><dd>{{ $localStart->format('D d M Y') }} to {{ $localEnd->format('D d M Y') }}</dd></div>
            <div><dt>Departure</dt><dd>{{ $departure->code }}</dd></div>
            <div><dt>Travellers</dt><dd>{{ implode(', ', $mix) }}</dd></div>
        </dl>
        <dl class="travel-selector__lines travel-checkout__lines">
            @foreach ($lines as $line)
                <div class="{{ ($line['totalMinor'] ?? 0) < 0 ? 'is-credit' : '' }}">
                    <dt>
                        {{ $line['description'] ?? '' }}
                        @if (($line['type'] ?? '') === 'fare')
                            <small>{{ $line['quantity'] ?? 1 }} &times; {{ MoneyFormatter::format((int) ($line['unitAmountMinor'] ?? 0), $currency) }}</small>
                        @endif
                    </dt>
                    <dd>{{ MoneyFormatter::format((int) ($line['totalMinor'] ?? 0), $currency) }}</dd>
                </div>
            @endforeach
            @if ((int) ($snapshot['tax_minor'] ?? 0) > 0)
                <div><dt>Tax</dt><dd>{{ MoneyFormatter::format((int) $snapshot['tax_minor'], $currency) }}</dd></div>
            @endif
            <div class="travel-selector__total"><dt>Total</dt><dd>{{ MoneyFormatter::format($totalMinor, $currency) }}</dd></div>
            @if ($depositMinor > 0 && $depositMinor < $totalMinor)
                <div><dt>Deposit to secure</dt><dd>{{ MoneyFormatter::format($depositMinor, $currency) }}</dd></div>
            @endif
        </dl>
        <a class="travel-checkout__change" href="{{ route('travel-tours.storefront.tours.show', $tour->slug) }}#tour-departures-title">Change departure or travellers</a>
    </aside>
</div>
