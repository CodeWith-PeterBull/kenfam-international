@use('App\Modules\TravelTours\Bookings\Enums\ParticipantType')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentMethod')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $shift = $this->shift;
    $quote = $this->pricing->quote;
    $failure = $this->pricing->failure;
    $lastBooking = $this->lastBooking;
    $receipt = $this->receiptInstruction();
    $exponent = (int) ($shift?->currency_exponent ?? config('travel-tours.defaults.currency_decimals', 2));
    $currency = (string) ($shift?->currency ?? config('travel-tours.defaults.currency', 'KES'));
    $money = static fn (int $minor, ?string $code = null): string => MoneyFormatter::format($minor, $code ?? $currency, $exponent);
@endphp

<div id="travel-desk-terminal" class="travel-desk">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('desk')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel mb-4 travel-desk__shift" aria-labelledby="travel-desk-shift-title">
        <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-desk-shift-title" class="card-title mb-1">
                    @if ($shift)
                        <i class="ti ti-clock-check text-success me-1" aria-hidden="true"></i>Shift open on {{ $shift->register->name }}
                    @else
                        <i class="ti ti-clock-pause text-warning me-1" aria-hidden="true"></i>No open shift
                    @endif
                </h3>
                <p class="aureon-muted mb-0">
                    @if ($shift)
                        Opened {{ $shift->opened_at->format('d M Y, H:i') }} &middot; float {{ $money((int) $shift->opening_float_minor) }} &middot; expected in drawer <strong>{{ $money($this->expectedCashMinor()) }}</strong>
                    @else
                        Open a shift on a register before taking payment. Bookings and receipts are attributed to it.
                    @endif
                </p>
            </div>
            <div class="travel-admin-row-actions">
                @if ($shift)
                    <button type="button" class="btn btn-outline-secondary" wire:click="openMovementDialog"><i class="ti ti-arrows-exchange me-1" aria-hidden="true"></i>Cash in / out</button>
                    <button type="button" class="btn btn-outline-danger" wire:click="openCloseDialog"><i class="ti ti-lock me-1" aria-hidden="true"></i>Close shift</button>
                @elseif (auth()->user()->can('open', \App\Modules\TravelTours\PointOfBooking\Models\BookingShift::class))
                    <button type="button" class="btn btn-primary" wire:click="openShiftDialog" @disabled($this->registers->isEmpty())><i class="ti ti-lock-open me-1" aria-hidden="true"></i>Open shift</button>
                @endif
                @if (! $shift && $this->registers->isEmpty())
                    <span class="aureon-muted">No active registers; a travel manager must add one first.</span>
                @endif
            </div>
        </div>
    </section>

    @if ($lastBooking)
        <section class="card aureon-panel mb-4 travel-desk__done" aria-labelledby="travel-desk-done-title">
            <div class="card-body">
                <h3 id="travel-desk-done-title" class="card-title mb-2"><i class="ti ti-circle-check text-success me-1" aria-hidden="true"></i>{{ $lastBooking->booking_number }} recorded</h3>
                <p class="mb-3">
                    {{ $lastBooking->customer_name_snapshot }} &middot; {{ $lastBooking->participants->count() }} {{ Str::plural('traveller', $lastBooking->participants->count()) }} &middot; {{ $lastBooking->tour_name_snapshot }} &middot;
                    <span class="badge text-bg-light">{{ $lastBooking->status->label() }}</span> <span class="badge text-bg-light">{{ $lastBooking->payment_status->label() }}</span>
                    &middot; received {{ $money((int) $lastBooking->paid_minor, $lastBooking->currency) }} of {{ $money((int) $lastBooking->total_minor, $lastBooking->currency) }}
                </p>
                <div class="travel-admin-row-actions" @if ($receipt) x-data x-init="if ({{ $receipt->automatic ? 'true' : 'false' }} && !sessionStorage.getItem('travel-receipt-{{ $lastBooking->ulid }}')) { sessionStorage.setItem('travel-receipt-{{ $lastBooking->ulid }}', '1'); window.open(@js($receipt->url), '_blank', 'noopener'); }" @endif>
                    @if ($receipt)
                        <a class="btn btn-primary" href="{{ $receipt->url }}" target="_blank" rel="noopener"><i class="ti ti-printer me-1" aria-hidden="true"></i>Print receipt ({{ $receipt->paperWidthMm }} mm)</a>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.bookings.index', ['q' => $lastBooking->booking_number]) }}"><i class="ti ti-eye me-1" aria-hidden="true"></i>Open booking</a>
                    <button type="button" class="btn btn-outline-primary" wire:click="startSale"><i class="ti ti-plus me-1" aria-hidden="true"></i>Next customer</button>
                </div>
            </div>
        </section>
    @endif

    <form class="row g-4" wire:submit="sell" novalidate>
        <div class="col-xl-8">
            <section class="card aureon-panel mb-4" aria-labelledby="travel-desk-selection-title">
                <div class="card-header"><h3 id="travel-desk-selection-title" class="card-title mb-0">1. Journey</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="desk-tour" class="form-label">Tour</label>
                            <select id="desk-tour" class="form-select @error('form.tourId') is-invalid @enderror" wire:model.live="form.tourId">
                                <option value="">Choose a tour</option>
                                @foreach ($this->tours as $tour)
                                    <option value="{{ $tour->getKey() }}">{{ $tour->name }} ({{ $tour->code }})</option>
                                @endforeach
                            </select>
                            @error('form.tourId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="desk-departure" class="form-label">Departure</label>
                            <select id="desk-departure" class="form-select @error('form.departureId') is-invalid @enderror" wire:model.live="form.departureId" @disabled($this->departures->isEmpty())>
                                <option value="">{{ $this->departures->isEmpty() ? 'Choose a tour first' : 'Choose a departure' }}</option>
                                @foreach ($this->departures as $departure)
                                    <option value="{{ $departure->getKey() }}">{{ $departure->starts_at->timezone($departure->timezone)->format('D d M Y') }} &middot; {{ $departure->code }}</option>
                                @endforeach
                            </select>
                            @error('form.departureId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($this->availableSeats() !== null)
                                <div class="form-text">{{ $this->availableSeats() }} {{ Str::plural('place', $this->availableSeats()) }} available</div>
                            @endif
                        </div>
                        @foreach (['adults' => 'Adults', 'children' => 'Children', 'infants' => 'Infants'] as $field => $label)
                            <div class="col-sm-4 col-md-2">
                                <label for="desk-{{ $field }}" class="form-label">{{ $label }}</label>
                                <input id="desk-{{ $field }}" type="number" min="{{ $field === 'adults' ? 1 : 0 }}" max="{{ (int) config('travel-tours.booking.maximum_participants', 20) }}" class="form-control @error('form.'.$field) is-invalid @enderror" wire:model.live.debounce.400ms="form.{{ $field }}">
                                @error('form.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <label for="desk-promotion" class="form-label">Promotion code <span class="aureon-muted">(optional)</span></label>
                            <input id="desk-promotion" type="text" class="form-control text-uppercase @error('form.promotionCode') is-invalid @enderror" wire:model.live.debounce.600ms="form.promotionCode" maxlength="40">
                            @error('form.promotionCode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="card aureon-panel mb-4" aria-labelledby="travel-desk-customer-title">
                <div class="card-header"><h3 id="travel-desk-customer-title" class="card-title mb-0">2. Customer and travellers</h3></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="desk-first-name" class="form-label">First name</label>
                            <input id="desk-first-name" type="text" class="form-control @error('form.firstName') is-invalid @enderror" wire:model="form.firstName" maxlength="80" autocomplete="off">
                            @error('form.firstName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="desk-last-name" class="form-label">Last name</label>
                            <input id="desk-last-name" type="text" class="form-control @error('form.lastName') is-invalid @enderror" wire:model="form.lastName" maxlength="80" autocomplete="off">
                            @error('form.lastName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="desk-phone" class="form-label">Phone</label>
                            <input id="desk-phone" type="tel" class="form-control @error('form.phone') is-invalid @enderror" wire:model="form.phone" maxlength="40" autocomplete="off">
                            @error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="desk-email" class="form-label">Email <span class="aureon-muted">(optional)</span></label>
                            <input id="desk-email" type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email" maxlength="160" autocomplete="off">
                            @error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input id="desk-lead-travels" class="form-check-input" type="checkbox" wire:model.live="form.leadTravels">
                        <label class="form-check-label" for="desk-lead-travels">The customer is traveller 1</label>
                    </div>
                    @foreach ($form->participants as $index => $participant)
                        @php
                            $type = ParticipantType::from($participant['type']);
                            $usesCustomer = $index === 0 && $form->leadTravels;
                        @endphp
                        <fieldset class="travel-desk__traveller" wire:key="desk-traveller-{{ $index }}">
                            <legend>Traveller {{ $index + 1 }} <small class="aureon-muted">{{ $type->label() }}</small></legend>
                            <div class="row g-2">
                                @unless ($usesCustomer)
                                    <div class="col-sm-4">
                                        <label class="form-label" for="desk-participant-{{ $index }}-first">First name</label>
                                        <input id="desk-participant-{{ $index }}-first" type="text" class="form-control form-control-sm @error("form.participants.{$index}.first_name") is-invalid @enderror" wire:model="form.participants.{{ $index }}.first_name" maxlength="80" autocomplete="off">
                                        @error("form.participants.{$index}.first_name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="desk-participant-{{ $index }}-last">Last name</label>
                                        <input id="desk-participant-{{ $index }}-last" type="text" class="form-control form-control-sm @error("form.participants.{$index}.last_name") is-invalid @enderror" wire:model="form.participants.{{ $index }}.last_name" maxlength="80" autocomplete="off">
                                        @error("form.participants.{$index}.last_name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @else
                                    <div class="col-sm-8 align-self-end"><span class="aureon-muted">Uses the customer's name and contact details.</span></div>
                                @endunless
                                <div class="col-sm-4">
                                    <label class="form-label" for="desk-participant-{{ $index }}-dob">Date of birth @if ($type === ParticipantType::Adult)<span class="aureon-muted">(optional)</span>@endif</label>
                                    <input id="desk-participant-{{ $index }}-dob" type="date" max="{{ now()->subDay()->toDateString() }}" class="form-control form-control-sm @error("form.participants.{$index}.date_of_birth") is-invalid @enderror" wire:model="form.participants.{{ $index }}.date_of_birth">
                                    @error("form.participants.{$index}.date_of_birth")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                    <label for="desk-special-requests" class="form-label mt-2">Special requests <span class="aureon-muted">(optional)</span></label>
                    <textarea id="desk-special-requests" rows="2" class="form-control @error('form.specialRequests') is-invalid @enderror" wire:model="form.specialRequests" maxlength="1000"></textarea>
                    @error('form.specialRequests')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card aureon-panel mb-4 travel-desk__summary" aria-labelledby="travel-desk-summary-title">
                <div class="card-header"><h3 id="travel-desk-summary-title" class="card-title mb-0">3. Price and settlement</h3></div>
                <div class="card-body">
                    @if ($quote)
                        <dl class="travel-detail-list mb-3">
                            @foreach ($quote->lines as $line)
                                <div><dt>{{ $line->description }}@if ($line->type === 'fare') <small class="aureon-muted">&times; {{ $line->quantity }}</small>@endif</dt><dd>{{ $money($line->totalMinor, $quote->currency) }}</dd></div>
                            @endforeach
                            @if ($quote->taxMinor > 0)
                                <div><dt>Tax</dt><dd>{{ $money($quote->taxMinor, $quote->currency) }}</dd></div>
                            @endif
                            <div><dt><strong>Total</strong></dt><dd><strong>{{ $money($quote->totalMinor, $quote->currency) }}</strong></dd></div>
                            <div><dt>Deposit to confirm</dt><dd>{{ $money($quote->depositMinor, $quote->currency) }}</dd></div>
                        </dl>
                    @elseif ($failure)
                        <p class="alert alert-warning py-2" role="status">{{ $failure }}</p>
                    @else
                        <p class="aureon-muted">Choose a tour, a departure, and the travellers to see the price.</p>
                    @endif

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="desk-payment-method" class="form-label">Paying by</label>
                            <select id="desk-payment-method" class="form-select" wire:model="form.paymentMethod">
                                @foreach (PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="desk-amount-received" class="form-label">Received now ({{ $currency }})</label>
                            <input id="desk-amount-received" type="text" inputmode="decimal" class="form-control @error('form.amountReceived') is-invalid @enderror" wire:model="form.amountReceived" placeholder="0.00">
                            @error('form.amountReceived')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="desk-payment-reference" class="form-label">Payment reference <span class="aureon-muted">(optional)</span></label>
                            <input id="desk-payment-reference" type="text" class="form-control @error('form.paymentReference') is-invalid @enderror" wire:model="form.paymentReference" maxlength="120">
                            @error('form.paymentReference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <p class="aureon-muted small mt-3 mb-3">Money entered here is recorded as received and confirmed at once. A cash amount goes into this shift's drawer. When the deposit is covered the booking is confirmed; otherwise it stays pending for the travel desk.</p>
                    @error('desk')
                        @if ($dialog === '')
                            <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
                        @endif
                    @enderror
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sell" @disabled(! $shift || ! $quote)>
                        <span wire:loading.remove wire:target="sell"><i class="ti ti-receipt me-1" aria-hidden="true"></i>Record booking</span>
                        <span wire:loading wire:target="sell">Recording…</span>
                    </button>
                </div>
            </section>
        </div>
    </form>

    @if ($dialog === 'open-shift')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-desk-open-title" wire:keydown.escape.window="closeDialog" x-data x-init="$nextTick(() => $el.querySelector('#shift-register')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="openShift" novalidate>
                    <div class="modal-header"><h3 id="travel-desk-open-title" class="modal-title fs-18">Open shift</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                    <div class="modal-body">
                        @error('desk')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="mb-3">
                            <label for="shift-register" class="form-label">Register</label>
                            <select id="shift-register" class="form-select @error('registerId') is-invalid @enderror" wire:model="registerId">
                                @foreach ($this->registers as $register)
                                    <option value="{{ $register->getKey() }}">{{ $register->name }} ({{ $register->code }})</option>
                                @endforeach
                            </select>
                            @error('registerId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="shift-float" class="form-label">Opening float ({{ $currency }})</label>
                            <input id="shift-float" type="text" inputmode="decimal" class="form-control @error('openingFloat') is-invalid @enderror" wire:model="openingFloat">
                            @error('openingFloat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="openShift">Open shift</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'movement')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-desk-movement-title" wire:keydown.escape.window="closeDialog" x-data x-init="$nextTick(() => $el.querySelector('#movement-amount')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="recordMovement" novalidate>
                    <div class="modal-header"><h3 id="travel-desk-movement-title" class="modal-title fs-18">Cash in / out</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                    <div class="modal-body">
                        @error('desk')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="movement-type" class="form-label">Direction</label>
                                <select id="movement-type" class="form-select" wire:model="movementType"><option value="cash_in">Cash in</option><option value="cash_out">Cash out</option></select>
                            </div>
                            <div class="col-sm-6">
                                <label for="movement-amount" class="form-label">Amount ({{ $currency }})</label>
                                <input id="movement-amount" type="text" inputmode="decimal" class="form-control @error('movementAmount') is-invalid @enderror" wire:model="movementAmount">
                                @error('movementAmount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="movement-reason" class="form-label">Reason</label>
                                <input id="movement-reason" type="text" class="form-control @error('movementReason') is-invalid @enderror" wire:model="movementReason" maxlength="500">
                                @error('movementReason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordMovement">Record movement</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'close-shift' && $shift)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-desk-close-title" wire:keydown.escape.window="closeDialog" x-data x-init="$nextTick(() => $el.querySelector('#shift-counted')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="closeShift" novalidate>
                    <div class="modal-header"><h3 id="travel-desk-close-title" class="modal-title fs-18">Close shift</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                    <div class="modal-body">
                        @error('desk')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <p class="aureon-muted">Expected in the drawer: <strong>{{ $money($this->expectedCashMinor()) }}</strong> (float, cash payments, and cash movements). Count the drawer and enter what is actually there.</p>
                        <div class="mb-3">
                            <label for="shift-counted" class="form-label">Counted cash ({{ $currency }})</label>
                            <input id="shift-counted" type="text" inputmode="decimal" class="form-control @error('countedCash') is-invalid @enderror" wire:model="countedCash">
                            @error('countedCash')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="shift-notes" class="form-label">Notes <span class="aureon-muted">(optional)</span></label>
                            <textarea id="shift-notes" rows="2" class="form-control @error('closingNotes') is-invalid @enderror" wire:model="closingNotes" maxlength="1000"></textarea>
                            @error('closingNotes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="closeShift">Close shift</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
