<div class="pob-workspace">
    @if(session('terminal-success'))<div class="pob-toast" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i>{{ session('terminal-success') }}</div>@endif
    @error('terminal')<div class="pob-alert" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i>{{ $message }}</div>@enderror

    @if(!$this->activeShift)
        <section class="pob-no-shift" aria-labelledby="pob-no-shift-title">
            <span><i class="ti ti-clock-pause" aria-hidden="true"></i></span>
            <p class="pob-eyebrow">Reception status</p><h1 id="pob-no-shift-title">No open shift assigned</h1>
            <p>A reception shift must be open for your account before bookings and payments can be recorded.</p>
            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_SHIFTS)<a class="pob-button" href="{{ route('property-booking.pob.admin.shifts.index') }}"><i class="ti ti-lock-open"></i>Open reception shift</a>@endcan
        </section>
    @else
        @php
            $shift = $this->activeShift;
            $quote = $this->selectedQuote;
            $held = $this->resumingBooking;
            $totalMinor = $held?->total_minor ?? $quote?->calculation->totalMinor ?? 0;
        @endphp
        <section class="pob-context" aria-label="Active reception context">
            <div><span class="pob-live-dot" aria-hidden="true"></span><div><small>Property</small><strong>{{ $shift->property->name }}</strong></div></div>
            <div><i class="ti ti-building-store" aria-hidden="true"></i><div><small>Register</small><strong>{{ $shift->register->name }} &middot; {{ $shift->register->code }}</strong></div></div>
            <div><i class="ti ti-user-check" aria-hidden="true"></i><div><small>Receptionist</small><strong>{{ $shift->receptionist->display_name }}</strong></div></div>
            <div><i class="ti ti-clock" aria-hidden="true"></i><div><small>Shift opened</small><strong>{{ $shift->opened_at->timezone($shift->property->timezone)->format('d M, H:i') }}</strong></div></div>
            @if($this->availableShifts->count() > 1)<label><span class="visually-hidden">Active shift</span><select wire:change="selectShift($event.target.value)">@foreach($this->availableShifts as $availableShift)<option value="{{ $availableShift->ulid }}" @selected($availableShift->ulid === $shiftUlid)>{{ $availableShift->property->code }} &middot; {{ $availableShift->register->code }} &middot; {{ $availableShift->receptionist->display_name }}</option>@endforeach</select></label>@endif
        </section>

        <div class="pob-terminal-grid">
            <section class="pob-pane pob-pane--availability" aria-labelledby="availability-title">
                <header><div><p class="pob-eyebrow">01 &middot; Availability</p><h2 id="availability-title">Find accommodation</h2></div><span>{{ $this->availableRates->count() }} matches</span></header>
                <div class="pob-search-grid">
                    <label><span class="pob-field-heading"><span>Arrival</span><button type="button" class="pob-quick-action" wire:click="useCurrentArrival" title="Use the current property time"><i class="ti ti-clock" aria-hidden="true"></i>Now</button></span><input type="date" wire:model.live.debounce.400ms="arrivalDate"><input type="time" wire:model.live.debounce.400ms="arrivalTime"></label>
                    <label><span>Departure</span><input type="date" wire:model.live.debounce.400ms="departureDate"><input type="time" wire:model.live.debounce.400ms="departureTime"></label>
                    <label><span>Adults</span><input type="number" min="1" max="20" wire:model.live.debounce.400ms="adults"></label>
                    <label><span>Children</span><input type="number" min="0" max="20" wire:model.live.debounce.400ms="children"></label>
                    <label><span>Infants</span><input type="number" min="0" max="10" wire:model.live.debounce.400ms="infants"></label>
                </div>
                <label class="pob-search"><i class="ti ti-search" aria-hidden="true"></i><span class="visually-hidden">Search accommodation</span><input type="search" wire:model.live.debounce.300ms="rateSearch" placeholder="Unit type, rate, or code"></label>

                <div class="pob-rate-list" wire:loading.class="is-loading" wire:target="arrivalDate,arrivalTime,departureDate,departureTime,adults,children,infants,rateSearch,useCurrentArrival">
                    @forelse($this->availableRates as $option)
                        @php($rate = $option['rate']) @php($rateQuote = $option['quote']) @php($cover = $rate->unitType->getFirstMedia('unit_type_cover'))
                        <button type="button" class="pob-rate-card {{ $selectedRatePlanId === $rate->id && !$held ? 'is-selected' : '' }}" wire:click="selectRate({{ $rate->id }})" wire:key="pob-rate-{{ $rate->id }}">
                            <span class="pob-rate-card__media">@if($cover)<img src="{{ $cover->getUrl('thumb') }}" alt="">@else<i class="ti ti-bed" aria-hidden="true"></i>@endif</span>
                            <span class="pob-rate-card__copy"><small>{{ $rate->unitType->code }} &middot; {{ $rate->code }}</small><strong>{{ $rate->unitType->name }}</strong><span>{{ $rate->name }} &middot; {{ $rateQuote->calculation->billableUnits }} {{ \Illuminate\Support\Str::plural($rateQuote->calculation->pricingUnit->label(), $rateQuote->calculation->billableUnits) }}</span></span>
                            <span class="pob-rate-card__price"><small>{{ $rateQuote->availableUnitCount }} open</small><strong>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($rateQuote->calculation->totalMinor, $rateQuote->calculation->currency) }}</strong></span>
                        </button>
                    @empty
                        <div class="pob-empty"><i class="ti ti-calendar-off" aria-hidden="true"></i><strong>No available rate</strong><span>Adjust the stay interval or occupancy.</span></div>
                    @endforelse
                </div>
            </section>

            <section class="pob-pane pob-pane--booking" aria-labelledby="booking-title">
                <header><div><p class="pob-eyebrow">02 &middot; Booking</p><h2 id="booking-title">Guest and stay</h2></div>@if($held)<span class="pob-status pob-status--hold">Resuming hold</span>@elseif($quote)<span class="pob-status">Selected</span>@endif</header>

                @if($held || $quote)
                    <article class="pob-selection">
                        <div><span><i class="ti ti-bed" aria-hidden="true"></i></span><div><small>{{ $held?->property_name ?? $quote->propertyName }}</small><h3>{{ $held?->stays->first()?->unit_type_name ?? $quote->unitTypeName }}</h3><p>{{ $held?->stays->first()?->rate_plan_name ?? $quote->ratePlanName }}</p></div></div>
                        <dl><div><dt>Arrival</dt><dd>{{ ($held?->starts_at ?? $quote->startsAt)->timezone($shift->property->timezone)->format('d M Y, H:i') }}</dd></div><div><dt>Departure</dt><dd>{{ ($held?->ends_at ?? $quote->endsAt)->timezone($shift->property->timezone)->format('d M Y, H:i') }}</dd></div><div><dt>Guests</dt><dd>{{ $held?->adult_count ?? $quote->adults }} adults, {{ $held?->child_count ?? $quote->children }} children</dd></div></dl>
                    </article>
                @else
                    <div class="pob-empty pob-empty--compact"><i class="ti ti-pointer" aria-hidden="true"></i><strong>Select accommodation</strong><span>Your current rate and stay summary will appear here.</span></div>
                @endif

                <div class="pob-section-heading"><div><h3>Guest</h3>@if($this->selectedGuest)<span class="pob-status"><i class="ti ti-check"></i>Selected</span>@endif</div><button type="button" class="pob-text-button" wire:click="toggleGuestForm"><i class="ti ti-user-plus"></i>{{ $showGuestForm ? 'Close form' : 'New guest' }}</button></div>
                @if($this->selectedGuest)
                    <div class="pob-selected-guest"><span>{{ str($this->selectedGuest->first_name)->substr(0, 1) }}{{ str($this->selectedGuest->last_name)->substr(0, 1) }}</span><div><strong>{{ $this->selectedGuest->fullName() }}</strong><small>{{ $this->selectedGuest->email ?: $this->selectedGuest->phone }}</small></div><button type="button" wire:click="$set('selectedGuestId', null)" aria-label="Remove selected guest" title="Remove guest"><i class="ti ti-x"></i></button></div>
                    @if($this->guestIdentityRequired && !$this->guestIdentitySatisfied)
                        <div class="pob-guest-form pob-identity-capture"><div><strong>ID or passport required</strong><small>This guest has no protected identity record.</small></div><div class="pob-form-grid"><label><span>Document type</span><select wire:model="guestIdentityType"><option value="">Select document</option>@foreach($this->identityTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('guestIdentityType')<small>{{ $message }}</small>@enderror</label><label><span>ID / passport number</span><input type="text" maxlength="180" autocomplete="off" wire:model="guestIdentityNumber">@error('guestIdentityNumber')<small>{{ $message }}</small>@enderror</label></div><button type="button" class="pob-button pob-button--small" wire:click="recordSelectedGuestIdentity" wire:loading.attr="disabled" wire:target="recordSelectedGuestIdentity"><i class="ti ti-shield-check"></i>Record identity</button></div>
                    @endif
                @elseif(!$showGuestForm)
                    <label class="pob-search"><i class="ti ti-user-search" aria-hidden="true"></i><span class="visually-hidden">Search guests</span><input type="search" wire:model.live.debounce.300ms="guestSearch" placeholder="Name, phone, or email"></label>
                    @if(strlen(trim($guestSearch)) >= 2)<div class="pob-guest-results">@forelse($this->guestResults as $guest)<button type="button" wire:click="selectGuest({{ $guest->id }})"><span>{{ str($guest->first_name)->substr(0, 1) }}{{ str($guest->last_name)->substr(0, 1) }}</span><div><strong>{{ $guest->fullName() }}</strong><small>{{ $guest->email ?: $guest->phone }}</small></div><i class="ti ti-chevron-right"></i></button>@empty<div class="pob-empty pob-empty--compact"><strong>No matching guest</strong></div>@endforelse</div>@endif
                @endif

                @if($showGuestForm)
                    <div class="pob-guest-form"><div class="pob-form-grid"><label><span>First name</span><input type="text" wire:model="guestFirstName">@error('guestFirstName')<small>{{ $message }}</small>@enderror</label><label><span>Middle name</span><input type="text" wire:model="guestMiddleName"></label><label><span>Last name</span><input type="text" wire:model="guestLastName">@error('guestLastName')<small>{{ $message }}</small>@enderror</label><label><span>Email</span><input type="email" wire:model="guestEmail">@error('guestEmail')<small>{{ $message }}</small>@enderror</label><label><span>Phone</span><input type="tel" wire:model="guestPhone">@error('guestPhone')<small>{{ $message }}</small>@enderror</label><label><span>Country</span><input type="text" maxlength="2" wire:model="guestCountryCode">@error('guestCountryCode')<small>{{ $message }}</small>@enderror</label><label><span>Document type{{ $this->guestIdentityRequired ? '' : ' (optional)' }}</span><select wire:model="guestIdentityType"><option value="">Select document</option>@foreach($this->identityTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>@error('guestIdentityType')<small>{{ $message }}</small>@enderror</label><label><span>ID / passport number{{ $this->guestIdentityRequired ? '' : ' (optional)' }}</span><input type="text" maxlength="180" autocomplete="off" wire:model="guestIdentityNumber">@error('guestIdentityNumber')<small>{{ $message }}</small>@enderror</label></div><button type="button" class="pob-button pob-button--small" wire:click="createGuest" wire:loading.attr="disabled" wire:target="createGuest"><i class="ti ti-user-check"></i>Create and select</button></div>
                @endif

                <div class="pob-form-grid pob-form-grid--notes"><label><span>Special requests</span><textarea rows="2" wire:model="specialRequests"></textarea></label><label><span>Internal note</span><textarea rows="2" wire:model="internalNote"></textarea></label></div>

                <div class="pob-hold-section"><div class="pob-section-heading"><div><h3>Active holds</h3><span>{{ $this->heldBookings->count() }}</span></div></div>@forelse($this->heldBookings as $booking)<article class="pob-hold"><div><strong>{{ $booking->booking_number }}</strong><span>{{ $booking->primaryGuest?->fullName() }} &middot; {{ $booking->stays->first()?->unit_type_name }}</span><small>Expires {{ $booking->hold_expires_at?->diffForHumans() }}</small></div><div><button type="button" wire:click="resumeHold({{ $booking->id }})" title="Resume hold"><i class="ti ti-player-play"></i></button><button type="button" wire:click="discardHold({{ $booking->id }})" wire:confirm="Discard this booking hold?" title="Discard hold"><i class="ti ti-trash"></i></button></div></article>@empty<p class="pob-muted">No active holds on this shift.</p>@endforelse</div>
            </section>

            <aside class="pob-pane pob-pane--payment" aria-labelledby="payment-title">
                <header><div><p class="pob-eyebrow">03 &middot; Payment</p><h2 id="payment-title">Settlement</h2></div><button type="button" class="pob-icon-button" wire:click="resetBooking" title="Reset booking" aria-label="Reset booking"><i class="ti ti-refresh"></i></button></header>
                <dl class="pob-totals"><div><dt>Accommodation</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($held?->accommodation_subtotal_minor ?? $quote?->calculation->subtotalMinor ?? 0, $shift->currency) }}</dd></div><div><dt>Tax {{ ($held?->tax_inclusive ?? $quote?->calculation->taxInclusive ?? true) ? 'included' : '' }}</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($held?->tax_minor ?? $quote?->calculation->taxMinor ?? 0, $shift->currency) }}</dd></div><div class="pob-totals__total"><dt>Total</dt><dd>{{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($totalMinor, $shift->currency) }}</dd></div></dl>

                <div class="pob-tender-heading"><h3>Payment tenders</h3><button type="button" class="pob-text-button" wire:click="addTender"><i class="ti ti-plus"></i>Add split</button></div>
                <div class="pob-tenders">
                    @foreach($tenders as $index => $tender)
                        <fieldset class="pob-tender" wire:key="pob-tender-{{ $index }}"><legend>Tender {{ $index + 1 }}</legend><button type="button" wire:click="removeTender({{ $index }})" aria-label="Remove tender {{ $index + 1 }}" title="Remove tender" @disabled(count($tenders) === 1)><i class="ti ti-x"></i></button>
                            <label><span>Method</span><select wire:model.live="tenders.{{ $index }}.method">@foreach($this->paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></label>
                            <label><span>Amount</span><span class="pob-money-input"><input type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.amount"><button type="button" wire:click="applyTotal({{ $index }})">Total</button></span>@error("tenders.$index.amount")<small>{{ $message }}</small>@enderror</label>
                            @if($tender['method'] === \App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod::Cash->value)<label><span>Cash tendered</span><input type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.tendered">@error("tenders.$index.tendered")<small>{{ $message }}</small>@enderror</label>@else<label><span>Transaction reference</span><input type="text" wire:model="tenders.{{ $index }}.reference">@error("tenders.$index.reference")<small>{{ $message }}</small>@enderror</label>@endif
                        </fieldset>
                    @endforeach
                </div>

                <div class="pob-payment-actions">
                    @if(!$held)<button type="button" class="pob-button pob-button--secondary" wire:click="holdBooking" wire:loading.attr="disabled" wire:target="holdBooking" @disabled(!$quote || !$this->guestIdentitySatisfied)><i class="ti ti-clock-pause"></i><span wire:loading.remove wire:target="holdBooking">Hold booking</span><span wire:loading wire:target="holdBooking">Holding...</span></button>@endif
                    <button type="button" class="pob-button" wire:click="completeBooking" wire:loading.attr="disabled" wire:target="completeBooking" @disabled((!$quote && !$held) || !$this->guestIdentitySatisfied || $totalMinor < 1)><i class="ti ti-circle-check"></i><span wire:loading.remove wire:target="completeBooking">{{ $held ? 'Settle held booking' : 'Complete booking' }}</span><span wire:loading wire:target="completeBooking">Processing...</span></button>
                </div>
                @error('tenders')<p class="pob-field-error">{{ $message }}</p>@enderror
            </aside>
        </div>
    @endif
</div>
