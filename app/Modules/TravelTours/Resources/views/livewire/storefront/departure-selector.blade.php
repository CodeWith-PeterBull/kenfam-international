@use('App\Modules\TravelTours\Bookings\Enums\ParticipantType')
@use('App\Modules\TravelTours\Scheduling\Enums\DepartureStatus')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $departures = $this->departures;
    $selected = $this->selectedDeparture;
    $quote = $this->pricing->quote;
    $failure = $this->pricing->failure;
    $plan = $selected ? $this->ratePlanFor($selected) : $this->defaultRatePlan();
    $currency = $plan?->currency ?? config('travel-tours.defaults.currency', 'KES');
    $maximum = $form->maximumParticipants();
@endphp
<section class="travel-selector" aria-labelledby="tour-departures-title">
    <p class="travel-eyebrow">Plan ahead</p>
    <h2 id="tour-departures-title">Available departures</h2>

    @if ($departures->isEmpty())
        <p class="travel-selector__empty">No departures are open for booking right now. Ask a travel specialist about private dates using the form on this page.</p>
    @else
        <fieldset class="travel-departure-list" wire:loading.attr="aria-busy" wire:target="departureId">
            <legend class="visually-hidden">Choose a departure</legend>
            @foreach ($departures as $departure)
                @php
                    $seats = $this->availability[$departure->getKey()];
                    $from = $this->fromPriceMinor($this->ratePlanFor($departure));
                    $soldOut = $seats->availableSeats === 0;
                    $isSelected = $selected?->is($departure) ?? false;
                @endphp
                <label class="travel-departure-card {{ $isSelected ? 'is-selected' : '' }} {{ $soldOut ? 'is-sold-out' : '' }}" data-departure-id="{{ $departure->getKey() }}">
                    <input class="travel-departure-card__input" type="radio" name="departure" value="{{ $departure->getKey() }}" wire:model.live="departureId" @disabled($soldOut) aria-describedby="departure-{{ $departure->getKey() }}-meta">
                    <span class="travel-departure-card__body">
                        <span class="travel-departure-card__dates">
                            <time datetime="{{ $departure->starts_at->toIso8601String() }}">{{ $departure->starts_at->timezone($departure->timezone)->format('D d M Y') }}</time>
                            <span aria-hidden="true">&rarr;</span>
                            <time datetime="{{ $departure->ends_at->toIso8601String() }}">{{ $departure->ends_at->timezone($departure->timezone)->format('D d M Y') }}</time>
                        </span>
                        <span class="travel-departure-card__meta" id="departure-{{ $departure->getKey() }}-meta">
                            <span>{{ $departure->code }}</span>
                            @if ($departure->status === DepartureStatus::Guaranteed)
                                <span class="travel-departure-card__flag"><i data-lucide="badge-check" aria-hidden="true"></i>Guaranteed departure</span>
                            @endif
                            @if ($soldOut)
                                <span class="travel-departure-card__seats is-sold-out">Fully booked</span>
                            @elseif ($seats->availableSeats <= 3)
                                <span class="travel-departure-card__seats is-scarce">Only {{ $seats->availableSeats }} {{ Str::plural('place', $seats->availableSeats) }} left</span>
                            @else
                                <span class="travel-departure-card__seats">{{ $seats->availableSeats }} places available</span>
                            @endif
                        </span>
                    </span>
                    <strong class="travel-departure-card__price">
                        @if ($from !== null)
                            <small>From</small> {{ MoneyFormatter::format($from, $currency) }}
                        @else
                            <small>Price on request</small>
                        @endif
                    </strong>
                </label>
            @endforeach
        </fieldset>

        <div class="travel-selector__panel" wire:loading.attr="aria-busy" wire:target="form.adults, form.children, form.infants, form.promotionCode, departureId">
            <form class="travel-selector__travellers" wire:submit.prevent novalidate aria-labelledby="travel-selector-travellers-title">
                <h3 id="travel-selector-travellers-title">Who is travelling?</h3>
                <div class="travel-selector__counts">
                    @foreach ([ParticipantType::Adult, ParticipantType::Child, ParticipantType::Infant] as $type)
                        @php
                            $field = $type->value === 'adult' ? 'adults' : ($type->value === 'child' ? 'children' : 'infants');
                            $band = $this->ageBand($type);
                        @endphp
                        <label for="selector-{{ $field }}">
                            <span>{{ Str::plural($type->label()) }}@if ($band) <small>({{ $band }})</small>@endif</span>
                            <input
                                id="selector-{{ $field }}"
                                type="number"
                                inputmode="numeric"
                                min="{{ $field === 'adults' ? 1 : 0 }}"
                                max="{{ $maximum }}"
                                step="1"
                                wire:model.live.debounce.400ms="form.{{ $field }}"
                                @if ($errors->has('form.'.$field)) aria-invalid="true" aria-describedby="selector-{{ $field }}-error" @endif
                            >
                            @error('form.'.$field)
                                <small class="travel-selector__error" id="selector-{{ $field }}-error" role="alert">{{ $message }}</small>
                            @enderror
                        </label>
                    @endforeach
                </div>
                <label for="selector-promotion-code" class="travel-selector__promotion">
                    <span>Promotion code <small>(optional)</small></span>
                    <input
                        id="selector-promotion-code"
                        type="text"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        maxlength="40"
                        wire:model.live.debounce.600ms="form.promotionCode"
                        @if ($errors->has('form.promotionCode')) aria-invalid="true" aria-describedby="selector-promotion-code-error" @endif
                    >
                    @error('form.promotionCode')
                        <small class="travel-selector__error" id="selector-promotion-code-error" role="alert">{{ $message }}</small>
                    @enderror
                </label>
            </form>

            <div class="travel-selector__quote" aria-live="polite">
                <h3>Your price</h3>
                @if ($quote)
                    <dl class="travel-selector__lines">
                        @foreach ($quote->lines as $line)
                            <div class="{{ $line->totalMinor < 0 ? 'is-credit' : '' }}">
                                <dt>
                                    {{ $line->description }}
                                    @if ($line->type === 'fare')
                                        <small>{{ $line->quantity }} &times; {{ MoneyFormatter::format($line->unitAmountMinor, $quote->currency) }}</small>
                                    @endif
                                </dt>
                                <dd>{{ MoneyFormatter::format($line->totalMinor, $quote->currency) }}</dd>
                            </div>
                        @endforeach
                        @if ($quote->taxMinor > 0)
                            <div>
                                <dt>{{ $plan?->tax_inclusive ? 'Includes tax' : 'Tax' }}</dt>
                                <dd>{{ MoneyFormatter::format($quote->taxMinor, $quote->currency) }}</dd>
                            </div>
                        @endif
                        <div class="travel-selector__total">
                            <dt>Total for {{ $quote->participants->participants() }} {{ Str::plural('traveller', $quote->participants->participants()) }}</dt>
                            <dd>{{ MoneyFormatter::format($quote->totalMinor, $quote->currency) }}</dd>
                        </div>
                    </dl>
                    @if ($quote->depositMinor > 0 && $quote->depositMinor < $quote->totalMinor)
                        <p class="travel-selector__deposit">
                            <i data-lucide="shield-check" aria-hidden="true"></i>
                            <span>Secure your places with a deposit of <strong>{{ MoneyFormatter::format($quote->depositMinor, $quote->currency) }}</strong>{{ $plan && $plan->balance_due_days > 0 ? '; the balance is due '.$plan->balance_due_days.' days before departure.' : '.' }}</span>
                        </p>
                    @endif
                    <p class="travel-selector__note">Prices are confirmed at booking for the dates shown. Infants share a seat and are not counted against places.</p>
                @elseif ($failure)
                    <p class="travel-selector__failure" role="status"><i data-lucide="info" aria-hidden="true"></i><span>{{ $failure }}</span></p>
                    @if ($selected === null || $failure === 'This departure is fully booked.')
                        <p class="travel-selector__note">Ask a travel specialist about waitlists or private dates using the form on this page.</p>
                    @endif
                @elseif ($selected === null)
                    <p class="travel-selector__failure" role="status"><i data-lucide="info" aria-hidden="true"></i><span>Every listed departure is fully booked. Ask a travel specialist about waitlists or private dates using the form on this page.</span></p>
                @else
                    <p class="travel-selector__failure" role="status"><i data-lucide="info" aria-hidden="true"></i><span>Adjust the traveller details above to see a price.</span></p>
                @endif
                @error('selection')
                    <p class="travel-selector__error travel-selector__error--block" role="alert">{{ $message }}</p>
                @enderror
                <button
                    type="button"
                    class="travel-button travel-button--wide travel-selector__continue"
                    wire:click="continue"
                    wire:loading.attr="disabled"
                    @disabled(! $quote)
                    aria-describedby="travel-selector-continue-hint"
                >
                    <span wire:loading.remove wire:target="continue">Continue to checkout</span>
                    <span wire:loading wire:target="continue">Reserving your places&hellip;</span>
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                </button>
                <p class="travel-selector__note" id="travel-selector-continue-hint">Your places are held for {{ (int) config('travel-tours.booking.hold_minutes', 15) }} minutes while you enter traveller details. No payment is taken online.</p>
            </div>
        </div>
    @endif
</section>
