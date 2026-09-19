@use('App\Modules\TravelTours\Bookings\Enums\PaymentMethod')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@use('App\Modules\TravelTours\Support\TravelToursPermission')
<div class="pob-workspace">
    @if (session('terminal-success'))
        <div class="pob-toast" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i>{{ session('terminal-success') }}</div>
    @endif
    @error('terminal')
        <div class="pob-alert" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i>{{ $message }}</div>
    @enderror

    @if (! $this->activeShift)
        <section class="pob-no-shift" aria-labelledby="pob-no-shift-title">
            <span><i class="ti ti-clock-pause" aria-hidden="true"></i></span>
            <p class="pob-eyebrow">Desk status</p>
            <h1 id="pob-no-shift-title">No open shift assigned</h1>
            <p>A booking-desk shift must be open for your account before bookings and payments can be recorded.</p>
            @can(TravelToursPermission::MANAGE_SHIFTS)
                <a class="pob-button" href="{{ route('travel-tours.pob.admin.shifts.index') }}"><i class="ti ti-lock-open" aria-hidden="true"></i>Open a booking shift</a>
            @endcan
        </section>
    @else
        @php
            $shift = $this->activeShift;
            $option = $this->selectedOption;
            $quote = $this->selectedQuote;
            $held = $this->resumingBooking;
            $currency = $shift->currency;
            $exponent = (int) $shift->currency_exponent;
            $money = static fn (int $minor): string => MoneyFormatter::format($minor, $currency, $exponent);
            $totalMinor = $held?->total_minor ?? $quote?->totalMinor ?? 0;
            $balanceMinor = $held ? (int) $held->balance_minor : ($quote?->totalMinor ?? 0);
            $depositMinor = $held ? max((int) $held->deposit_required_minor - (int) $held->paid_minor, 0) : ($quote?->depositMinor ?? 0);
            $timezone = $held?->departure_timezone_snapshot ?? $option['departure']->timezone ?? config('travel-tours.defaults.timezone', 'UTC');
        @endphp
        <section class="pob-context" aria-label="Active desk context">
            <div><span class="pob-live-dot" aria-hidden="true"></span><div><small>Register</small><strong>{{ $shift->register->name }} &middot; {{ $shift->register->code }}</strong></div></div>
            <div><i class="ti ti-user-check" aria-hidden="true"></i><div><small>Operator</small><strong>{{ $shift->operator->display_name }}</strong></div></div>
            <div><i class="ti ti-clock" aria-hidden="true"></i><div><small>Shift opened</small><strong>{{ $shift->opened_at->timezone(config('travel-tours.defaults.timezone', 'UTC'))->format('d M, H:i') }}</strong></div></div>
            <div><i class="ti ti-clock-pause" aria-hidden="true"></i><div><small>Active holds</small><strong>{{ $this->heldBookings->count() }}</strong></div></div>
            @if ($this->availableShifts->count() > 1)
                <label>
                    <span class="visually-hidden">Active shift</span>
                    <select wire:change="selectShift($event.target.value)">
                        @foreach ($this->availableShifts as $availableShift)
                            <option value="{{ $availableShift->ulid }}" @selected($availableShift->ulid === $shiftUlid)>{{ $availableShift->register->code }} &middot; {{ $availableShift->operator->display_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </section>

        <div class="pob-terminal-grid">
            <section class="pob-pane pob-pane--availability" aria-labelledby="availability-title">
                <header>
                    <div><p class="pob-eyebrow">01 &middot; Departures</p><h2 id="availability-title">Find a departure</h2></div>
                    <span>{{ $this->availableDepartures->count() }} matches</span>
                </header>
                <div class="pob-search-grid">
                    <label>
                        <span class="pob-field-heading"><span>Departing from</span><button type="button" class="pob-quick-action" wire:click="useToday" title="Departures from today"><i class="ti ti-calendar" aria-hidden="true"></i>Today</button></span>
                        <input type="date" wire:model.live.debounce.400ms="dateFrom">
                    </label>
                    <label><span>Until</span><input type="date" wire:model.live.debounce.400ms="dateTo"></label>
                    <label><span>Adults</span><input type="number" min="1" max="50" wire:model.live.debounce.400ms="adults"></label>
                    <label><span>Children</span><input type="number" min="0" max="50" wire:model.live.debounce.400ms="children"></label>
                    <label><span>Infants</span><input type="number" min="0" max="50" wire:model.live.debounce.400ms="infants"></label>
                </div>
                <label class="pob-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <span class="visually-hidden">Search tours</span>
                    <input type="search" wire:model.live.debounce.300ms="tourSearch" placeholder="Tour name, tour code, or departure code">
                </label>

                <div class="pob-rate-list" wire:loading.class="is-loading" wire:target="dateFrom,dateTo,adults,children,infants,tourSearch,useToday">
                    @forelse ($this->availableDepartures as $item)
                        @php
                            $departure = $item['departure'];
                            $cover = $departure->tour->getFirstMedia('tour_cover');
                            $nights = max($departure->starts_at->diffInDays($departure->ends_at), 0);
                        @endphp
                        <button type="button" class="pob-rate-card {{ $selectedDepartureId === $departure->id && ! $held ? 'is-selected' : '' }}" wire:click="selectDeparture({{ $departure->id }})" wire:key="pob-departure-{{ $departure->id }}">
                            <span class="pob-rate-card__media">
                                @if ($cover)
                                    <img src="{{ $cover->hasGeneratedConversion('thumb') ? $cover->getUrl('thumb') : $cover->getUrl() }}" alt="" loading="lazy">
                                @else
                                    <i class="ti ti-map-route" aria-hidden="true"></i>
                                @endif
                            </span>
                            <span class="pob-rate-card__copy">
                                <small>{{ $departure->tour->code }} &middot; {{ $departure->code }}</small>
                                <strong>{{ $departure->tour->name }}</strong>
                                <span>{{ $departure->starts_at->timezone($departure->timezone)->format('D d M Y') }} &ndash; {{ $departure->ends_at->timezone($departure->timezone)->format('D d M Y') }} &middot; {{ $nights }} {{ Str::plural('night', $nights) }}</span>
                            </span>
                            <span class="pob-rate-card__price">
                                <small>{{ $item['seats'] }} {{ Str::plural('seat', $item['seats']) }} left</small>
                                <strong>{{ MoneyFormatter::format($item['quote']->totalMinor, $item['quote']->currency, $exponent) }}</strong>
                            </span>
                        </button>
                    @empty
                        <div class="pob-empty"><i class="ti ti-calendar-off" aria-hidden="true"></i><strong>No available departure</strong><span>Widen the dates or change the traveller mix.</span></div>
                    @endforelse
                </div>
            </section>

            <section class="pob-pane pob-pane--booking" aria-labelledby="booking-title">
                <header>
                    <div><p class="pob-eyebrow">02 &middot; Booking</p><h2 id="booking-title">Customer and travellers</h2></div>
                    @if ($held)
                        <span class="pob-status pob-status--hold">Resuming hold</span>
                    @elseif ($option)
                        <span class="pob-status">Selected</span>
                    @endif
                </header>

                @if ($held)
                    <article class="pob-selection">
                        <div><span><i class="ti ti-ticket" aria-hidden="true"></i></span><div><small>{{ $held->booking_number }}</small><h3>{{ $held->tour_name_snapshot }}</h3><p>{{ $held->customer_name_snapshot }}</p></div></div>
                        <dl>
                            <div><dt>Departs</dt><dd>{{ $held->departure_starts_at_snapshot->timezone($timezone)->format('d M Y') }}</dd></div>
                            <div><dt>Returns</dt><dd>{{ $held->departure_ends_at_snapshot->timezone($timezone)->format('d M Y') }}</dd></div>
                            <div><dt>Travellers</dt><dd>{{ $held->adult_count }} adults, {{ $held->child_count }} children, {{ $held->infant_count }} infants</dd></div>
                        </dl>
                    </article>
                @elseif ($option)
                    @php($selected = $option['departure'])
                    <article class="pob-selection">
                        <div><span><i class="ti ti-map-route" aria-hidden="true"></i></span><div><small>{{ $selected->tour->code }} &middot; {{ $selected->code }}</small><h3>{{ $selected->tour->name }}</h3><p>{{ $selected->ratePlan?->name ?? 'Standard fares' }}</p></div></div>
                        <dl>
                            <div><dt>Departs</dt><dd>{{ $selected->starts_at->timezone($timezone)->format('d M Y') }}</dd></div>
                            <div><dt>Returns</dt><dd>{{ $selected->ends_at->timezone($timezone)->format('d M Y') }}</dd></div>
                            <div><dt>Travellers</dt><dd>{{ $adults }} adults, {{ $children }} children, {{ $infants }} infants</dd></div>
                        </dl>
                    </article>
                @else
                    <div class="pob-empty pob-empty--compact"><i class="ti ti-pointer" aria-hidden="true"></i><strong>Select a departure</strong><span>The tour, dates, and traveller summary will appear here.</span></div>
                @endif

                <div class="pob-section-heading">
                    <div><h3>Customer</h3>@if ($this->selectedCustomer)<span class="pob-status"><i class="ti ti-check" aria-hidden="true"></i>Selected</span>@endif</div>
                    @unless ($held)
                        <button type="button" class="pob-text-button" wire:click="toggleCustomerForm"><i class="ti ti-user-plus" aria-hidden="true"></i>{{ $showCustomerForm ? 'Close form' : 'New customer' }}</button>
                    @endunless
                </div>
                @if ($this->selectedCustomer)
                    <div class="pob-selected-guest">
                        <span aria-hidden="true">{{ str($this->selectedCustomer->first_name)->substr(0, 1) }}{{ str($this->selectedCustomer->last_name)->substr(0, 1) }}</span>
                        <div><strong>{{ $this->selectedCustomer->full_name }}</strong><small>{{ $this->selectedCustomer->phone ?: $this->selectedCustomer->email }}</small></div>
                        @unless ($held)
                            <button type="button" wire:click="clearCustomer" aria-label="Remove selected customer" title="Remove customer"><i class="ti ti-x" aria-hidden="true"></i></button>
                        @endunless
                    </div>
                @elseif (! $showCustomerForm)
                    <label class="pob-search">
                        <i class="ti ti-user-search" aria-hidden="true"></i>
                        <span class="visually-hidden">Search customers</span>
                        <input type="search" wire:model.live.debounce.300ms="customerSearch" placeholder="Name, phone, or email">
                    </label>
                    @if (mb_strlen(trim($customerSearch)) >= 2)
                        <div class="pob-guest-results">
                            @forelse ($this->customerResults as $customer)
                                <button type="button" wire:click="selectCustomer({{ $customer->id }})" wire:key="pob-customer-{{ $customer->id }}">
                                    <span aria-hidden="true">{{ str($customer->first_name)->substr(0, 1) }}{{ str($customer->last_name)->substr(0, 1) }}</span>
                                    <div><strong>{{ $customer->full_name }}</strong><small>{{ $customer->phone ?: $customer->email }}</small></div>
                                    <i class="ti ti-chevron-right" aria-hidden="true"></i>
                                </button>
                            @empty
                                <div class="pob-empty pob-empty--compact"><strong>No matching customer</strong></div>
                            @endforelse
                        </div>
                    @endif
                @endif

                @if ($showCustomerForm)
                    <div class="pob-guest-form">
                        <div class="pob-form-grid">
                            <label><span>First name</span><input type="text" wire:model="customerFirstName">@error('customerFirstName')<small>{{ $message }}</small>@enderror</label>
                            <label><span>Last name</span><input type="text" wire:model="customerLastName">@error('customerLastName')<small>{{ $message }}</small>@enderror</label>
                            <label><span>Phone</span><input type="tel" wire:model="customerPhone">@error('customerPhone')<small>{{ $message }}</small>@enderror</label>
                            <label><span>Email (optional)</span><input type="email" wire:model="customerEmail">@error('customerEmail')<small>{{ $message }}</small>@enderror</label>
                        </div>
                        <button type="button" class="pob-button pob-button--small" wire:click="createCustomer" wire:loading.attr="disabled" wire:target="createCustomer"><i class="ti ti-user-check" aria-hidden="true"></i>Create and select</button>
                    </div>
                @endif

                @unless ($held)
                    <div class="pob-section-heading">
                        <div><h3>Travellers</h3><span>{{ count($travellers) }}</span></div>
                        <label class="pob-check"><input type="checkbox" wire:model.live="leadTravels"><span>Customer travels</span></label>
                    </div>
                    <div class="pob-travellers">
                        @foreach ($travellers as $index => $traveller)
                            @php($usesCustomer = $index === 0 && $leadTravels)
                            <fieldset class="pob-traveller" wire:key="pob-traveller-{{ $index }}">
                                <legend>Traveller {{ $index + 1 }} &middot; {{ ucfirst($traveller['type']) }}</legend>
                                @if ($usesCustomer)
                                    <p class="pob-muted">{{ $this->selectedCustomer?->full_name ?? 'The selected customer' }} travels as the lead.</p>
                                @else
                                    <div class="pob-form-grid">
                                        <label><span>First name</span><input type="text" wire:model="travellers.{{ $index }}.first_name">@error("travellers.{$index}.first_name")<small>{{ $message }}</small>@enderror</label>
                                        <label><span>Last name</span><input type="text" wire:model="travellers.{{ $index }}.last_name">@error("travellers.{$index}.last_name")<small>{{ $message }}</small>@enderror</label>
                                    </div>
                                @endif
                                @if ($traveller['type'] !== 'adult' || ! $usesCustomer)
                                    <label class="pob-traveller__dob"><span>Date of birth{{ $traveller['type'] === 'adult' ? ' (optional)' : '' }}</span><input type="date" wire:model="travellers.{{ $index }}.date_of_birth">@error("travellers.{$index}.date_of_birth")<small>{{ $message }}</small>@enderror</label>
                                @endif
                            </fieldset>
                        @endforeach
                    </div>

                    <div class="pob-form-grid pob-form-grid--notes">
                        <label><span>Special requests</span><textarea rows="2" wire:model="specialRequests"></textarea>@error('specialRequests')<small>{{ $message }}</small>@enderror</label>
                    </div>
                @endunless

                <div class="pob-hold-section">
                    <div class="pob-section-heading"><div><h3>Active holds</h3><span>{{ $this->heldBookings->count() }}</span></div></div>
                    @forelse ($this->heldBookings as $booking)
                        <article class="pob-hold" wire:key="pob-hold-{{ $booking->id }}">
                            <div>
                                <strong>{{ $booking->booking_number }}</strong>
                                <span>{{ $booking->customer_name_snapshot }} &middot; {{ $booking->tour_name_snapshot }}</span>
                                <small>{{ $money((int) $booking->total_minor) }} &middot; expires {{ $booking->pending_expires_at?->diffForHumans() ?? 'when the shift closes' }}</small>
                            </div>
                            <div>
                                <button type="button" wire:click="resumeHold({{ $booking->id }})" title="Resume hold" aria-label="Resume hold {{ $booking->booking_number }}"><i class="ti ti-player-play" aria-hidden="true"></i></button>
                                <button type="button" wire:click="discardHold({{ $booking->id }})" wire:confirm="Discard this hold and release its seats?" title="Discard hold" aria-label="Discard hold {{ $booking->booking_number }}"><i class="ti ti-trash" aria-hidden="true"></i></button>
                            </div>
                        </article>
                    @empty
                        <p class="pob-muted">No active holds on this shift.</p>
                    @endforelse
                </div>
            </section>

            <aside class="pob-pane pob-pane--payment" aria-labelledby="payment-title">
                <header>
                    <div><p class="pob-eyebrow">03 &middot; Payment</p><h2 id="payment-title">Settlement</h2></div>
                    <button type="button" class="pob-icon-button" wire:click="resetBooking" title="Reset booking" aria-label="Reset booking"><i class="ti ti-refresh" aria-hidden="true"></i></button>
                </header>

                @unless ($held)
                    <label class="pob-promotion">
                        <span>Promotion code (optional)</span>
                        <input type="text" wire:model.live.debounce.500ms="promotionCode" autocomplete="off" maxlength="40" @if ($errors->has('promotionCode')) aria-invalid="true" @endif>
                        @error('promotionCode')<small class="pob-field-error">{{ $message }}</small>@enderror
                    </label>
                @endunless

                <dl class="pob-totals">
                    <div><dt>Fares</dt><dd>{{ $money($held ? (int) $held->subtotal_minor + (int) $held->extras_total_minor : ($quote?->subtotalMinor ?? 0)) }}</dd></div>
                    @if (($held?->discount_total_minor ?? $quote?->discountMinor ?? 0) > 0)
                        <div><dt>Discount</dt><dd>&minus;{{ $money($held ? (int) $held->discount_total_minor : (int) $quote->discountMinor) }}</dd></div>
                    @endif
                    <div><dt>Tax</dt><dd>{{ $money($held ? (int) $held->tax_total_minor : ($quote?->taxMinor ?? 0)) }}</dd></div>
                    <div class="pob-totals__total"><dt>Total</dt><dd>{{ $money($totalMinor) }}</dd></div>
                    @if ($held && $held->paid_minor > 0)
                        <div><dt>Paid so far</dt><dd>{{ $money((int) $held->paid_minor) }}</dd></div>
                        <div><dt>Balance</dt><dd>{{ $money($balanceMinor) }}</dd></div>
                    @endif
                    <div><dt>Deposit due now</dt><dd>{{ $money($depositMinor) }}</dd></div>
                </dl>

                <div class="pob-tender-heading"><h3>Payment tenders</h3><button type="button" class="pob-text-button" wire:click="addTender"><i class="ti ti-plus" aria-hidden="true"></i>Add split</button></div>
                <div class="pob-tenders">
                    @foreach ($tenders as $index => $tender)
                        <fieldset class="pob-tender" wire:key="pob-tender-{{ $index }}">
                            <legend>Tender {{ $index + 1 }}</legend>
                            <button type="button" wire:click="removeTender({{ $index }})" aria-label="Remove tender {{ $index + 1 }}" title="Remove tender" @disabled(count($tenders) === 1)><i class="ti ti-x" aria-hidden="true"></i></button>
                            <label>
                                <span>Method</span>
                                <select wire:model.live="tenders.{{ $index }}.method">
                                    @foreach ($this->paymentMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Amount</span>
                                <span class="pob-money-input">
                                    <input type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.amount">
                                    <button type="button" wire:click="applyDeposit({{ $index }})" title="Apply the deposit">Deposit</button>
                                    <button type="button" wire:click="applyTotal({{ $index }})" title="Apply the full balance">Total</button>
                                </span>
                                @error("tenders.$index.amount")<small>{{ $message }}</small>@enderror
                            </label>
                            @if ($tender['method'] === PaymentMethod::Cash->value)
                                <label wire:key="pob-tender-{{ $index }}-tendered"><span>Cash tendered</span><input type="text" inputmode="decimal" wire:model="tenders.{{ $index }}.tendered">@error("tenders.$index.tendered")<small>{{ $message }}</small>@enderror</label>
                            @else
                                <label wire:key="pob-tender-{{ $index }}-reference"><span>Transaction reference</span><input type="text" wire:model="tenders.{{ $index }}.reference">@error("tenders.$index.reference")<small>{{ $message }}</small>@enderror</label>
                            @endif
                        </fieldset>
                    @endforeach
                </div>

                <div class="pob-payment-actions">
                    @unless ($held)
                        <button type="button" class="pob-button pob-button--secondary" wire:click="holdBooking" wire:loading.attr="disabled" wire:target="holdBooking" @disabled(! $quote || ! $this->selectedCustomer)><i class="ti ti-clock-pause" aria-hidden="true"></i><span wire:loading.remove wire:target="holdBooking">Hold booking</span><span wire:loading wire:target="holdBooking">Holding...</span></button>
                    @endunless
                    <button type="button" class="pob-button" wire:click="completeBooking" wire:loading.attr="disabled" wire:target="completeBooking" @disabled((! $quote && ! $held) || (! $held && ! $this->selectedCustomer) || $balanceMinor < 1)><i class="ti ti-circle-check" aria-hidden="true"></i><span wire:loading.remove wire:target="completeBooking">{{ $held ? 'Settle held booking' : 'Complete booking' }}</span><span wire:loading wire:target="completeBooking">Processing...</span></button>
                </div>
                @error('tenders')<p class="pob-field-error">{{ $message }}</p>@enderror
            </aside>
        </div>
    @endif
</div>
