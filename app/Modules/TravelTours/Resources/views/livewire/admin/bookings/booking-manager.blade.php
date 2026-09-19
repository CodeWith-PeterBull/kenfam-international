@use('App\Modules\TravelTours\Bookings\Enums\BookingStatus')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentMethod')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus')
@use('App\Modules\TravelTours\Bookings\Enums\PaymentStatus')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $bookings = $this->bookings;
    $selected = $this->selected;
    $counts = $this->statusCounts;
    $user = auth()->user();
    $money = static fn (int $minor, $booking): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
    $statusClass = static fn (BookingStatus $status): string => match ($status) {
        BookingStatus::Confirmed, BookingStatus::Completed => 'travel-status--active',
        BookingStatus::Pending, BookingStatus::Held => 'travel-status--review',
        default => 'travel-status--muted',
    };
    $paymentClass = static fn (PaymentStatus $status): string => match ($status) {
        PaymentStatus::Paid => 'travel-status--active',
        PaymentStatus::Partial, PaymentStatus::PartiallyRefunded => 'travel-status--review',
        default => 'travel-status--muted',
    };
    $listTargets = 'search, statusFilter, paymentFilter, channelFilter, attentionFilter, clearFilters, gotoPage, nextPage, previousPage, confirmBooking';
    $canRecord = $selected && $user->can('recordPayment', $selected) && ! $selected->status->isTerminal();
    $canConfirmPayments = $selected && $user->can('confirmPayment', $selected);
    $canRefund = $selected && $user->can('refund', $selected) && $selected->paid_minor > $selected->refunded_minor;
    $canManage = $selected && $user->can('update', $selected);
@endphp

<div id="travel-booking-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('management')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-bookings-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-bookings-title" class="card-title mb-1">Bookings</h3>
                <p class="aureon-muted mb-0">{{ number_format($bookings->total()) }} {{ Str::plural('booking', $bookings->total()) }} across every channel, newest first</p>
            </div>
            <span class="travel-admin-refresh" wire:loading.inline-flex wire:target="{{ $listTargets }}" role="status">
                <span class="spinner-border" aria-hidden="true"></span>Updating list…
            </span>
        </div>

        <div class="card-body border-bottom">
            <div class="travel-admin-filters">
                <div class="travel-admin-filter travel-admin-filter--search">
                    <label for="booking-search" class="form-label">Search bookings</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
                        <input id="booking-search" type="search" class="form-control" placeholder="Booking number, customer, or tour" wire:model.live.debounce.400ms="search">
                    </div>
                </div>
                <div class="travel-admin-filter">
                    <label for="booking-status-filter" class="form-label">Status</label>
                    <select id="booking-status-filter" class="form-select" wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="booking-payment-filter" class="form-label">Payment</label>
                    <select id="booking-payment-filter" class="form-select" wire:model.live="paymentFilter">
                        <option value="">All payment states</option>
                        @foreach ($paymentStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="booking-channel-filter" class="form-label">Channel</label>
                    <select id="booking-channel-filter" class="form-select" wire:model.live="channelFilter">
                        <option value="">All channels</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="booking-attention-filter" class="form-label">Needs attention</label>
                    <select id="booking-attention-filter" class="form-select" wire:model.live="attentionFilter">
                        <option value="">Everything</option>
                        <option value="payments">Payments to confirm</option>
                        <option value="bookings">Bookings to confirm</option>
                        <option value="balance">Confirmed with a balance</option>
                        <option value="departing">Departing within 30 days</option>
                    </select>
                </div>
                <div class="travel-admin-filter travel-admin-filter--action">
                    <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters" wire:loading.attr="disabled" wire:target="clearFilters"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button>
                </div>
            </div>
        </div>
        <div class="travel-catalog-status-counts" aria-label="Bookings by state">
            @foreach ($counts['statuses'] as $value => $count)
                <span><strong>{{ number_format($count) }}</strong>{{ BookingStatus::from($value)->label() }}</span>
            @endforeach
            <span><strong>{{ number_format($counts['awaiting_payment_confirmation']) }}</strong>{{ Str::plural('payment', $counts['awaiting_payment_confirmation']) }} to confirm</span>
        </div>

        <div class="table-responsive travel-admin-list" wire:loading.attr="aria-busy" wire:target="{{ $listTargets }}">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col" class="travel-admin-index">#</th>
                        <th scope="col">Booking</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Departure</th>
                        <th scope="col" class="text-center">Travellers</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Payment</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        @php
                            $travellers = $booking->adult_count + $booking->child_count + $booking->infant_count;
                            $received = $booking->paid_minor - $booking->refunded_minor;
                        @endphp
                        <tr wire:key="booking-{{ $booking->getKey() }}">
                            <td class="travel-admin-index">{{ $bookings->firstItem() + $loop->index }}</td>
                            <td>
                                <strong class="d-block">{{ $booking->booking_number }}</strong>
                                <small class="aureon-muted">{{ $booking->channel->label() }} &middot; {{ $booking->placed_at->format('d M Y, H:i') }}</small>
                            </td>
                            <td>
                                <span class="d-block text-break">{{ $booking->customer_name_snapshot }}</span>
                                <small class="aureon-muted text-break">{{ $booking->customer_email_snapshot ?: $booking->customer_phone_snapshot }}</small>
                            </td>
                            <td>
                                <span class="d-block text-break">{{ $booking->tour_name_snapshot }}</span>
                                <small class="aureon-muted">{{ $booking->departure_starts_at_snapshot->timezone($booking->departure_timezone_snapshot)->format('d M Y') }}@if ($booking->departure) &middot; {{ $booking->departure->code }}@endif</small>
                            </td>
                            <td class="text-center">{{ $travellers }}</td>
                            <td class="text-end">
                                <strong class="d-block">{{ $money($booking->total_minor, $booking) }}</strong>
                                <small class="aureon-muted">{{ $received > 0 ? $money($received, $booking).' received' : 'Nothing received' }}</small>
                            </td>
                            <td><span class="travel-status {{ $paymentClass($booking->payment_status) }}">{{ $booking->payment_status->label() }}</span></td>
                            <td><span class="travel-status {{ $statusClass($booking->status) }}">{{ $booking->status->label() }}</span></td>
                            <td class="text-end">
                                <div class="travel-admin-row-actions justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openDetails({{ $booking->getKey() }})" title="View booking" aria-label="View {{ $booking->booking_number }}">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </button>
                                    @if ($user->can('recordPayment', $booking) && ! $booking->status->isTerminal())
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openPayment({{ $booking->getKey() }})" title="Record payment" aria-label="Record payment for {{ $booking->booking_number }}">
                                            <i class="ti ti-cash" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                    @if ($user->can('update', $booking) && $booking->status === BookingStatus::Pending)
                                        <button type="button" class="btn btn-sm btn-outline-success btn-icon" wire:click="confirmBooking({{ $booking->getKey() }})" wire:loading.attr="disabled" wire:target="confirmBooking" title="Confirm booking" aria-label="Confirm {{ $booking->booking_number }}">
                                            <i class="ti ti-check" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="travel-admin-empty">
                                    <i class="ti ti-ticket" aria-hidden="true"></i>
                                    <strong>No bookings match the current filters</strong>
                                    <span>Bookings arrive from the storefront and the booking desk.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="card-footer">{{ $bookings->links() }}</div>
        @endif
    </section>

    @if ($dialog === 'details' && $selected)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-booking-detail-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('.btn-close')?.focus())">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-booking-detail-title" class="modal-title fs-18">{{ $selected->booking_number }}</h3>
                            <p class="aureon-muted mb-0">
                                <span class="travel-status {{ $statusClass($selected->status) }}">{{ $selected->status->label() }}</span>
                                <span class="travel-status {{ $paymentClass($selected->payment_status) }}">{{ $selected->payment_status->label() }}</span>
                                {{ $selected->channel->label() }} &middot; placed {{ $selected->placed_at->format('d M Y, H:i') }}
                            </p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close booking details"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="travel-detail-grid">
                            <section aria-labelledby="travel-booking-detail-journey">
                                <h4 id="travel-booking-detail-journey">Journey</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Tour</dt><dd>{{ $selected->tour_name_snapshot }} <small class="aureon-muted">{{ $selected->tour_code_snapshot }}</small></dd></div>
                                    <div><dt>Departure</dt><dd>{{ $selected->departure?->code }} &middot; {{ $selected->departure_starts_at_snapshot->timezone($selected->departure_timezone_snapshot)->format('d M Y') }} to {{ $selected->departure_ends_at_snapshot->timezone($selected->departure_timezone_snapshot)->format('d M Y') }}</dd></div>
                                    <div><dt>Confirmation</dt><dd>{{ $selected->confirmation_mode->label() }}@if ($selected->pending_expires_at) &middot; expires {{ $selected->pending_expires_at->format('d M Y, H:i') }}@endif</dd></div>
                                    <div><dt>Special requests</dt><dd>{{ $selected->special_requests ?: 'None' }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-booking-detail-customer">
                                <h4 id="travel-booking-detail-customer">Customer</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Name</dt><dd>{{ $selected->customer_name_snapshot }}</dd></div>
                                    <div><dt>Email</dt><dd>{{ $selected->customer_email_snapshot ?: 'Not supplied' }}</dd></div>
                                    <div><dt>Phone</dt><dd>{{ $selected->customer_phone_snapshot ?: 'Not supplied' }}</dd></div>
                                    <div><dt>Prefers to pay by</dt><dd>{{ $selected->preferred_payment_method->label() }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-booking-detail-travellers">
                                <h4 id="travel-booking-detail-travellers">Travellers</h4>
                                <ol class="travel-detail-people">
                                    @foreach ($selected->participants->sortBy('sequence') as $participant)
                                        <li>
                                            <strong>{{ trim($participant->first_name.' '.$participant->last_name) }}</strong>
                                            <small class="aureon-muted">{{ $participant->participant_type->label() }}@if ($participant->is_lead) &middot; lead @endif@if ($participant->date_of_birth) &middot; born {{ $participant->date_of_birth->format('d M Y') }}@endif &middot; {{ $money($participant->allocated_price_minor, $selected) }}</small>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>
                            <section aria-labelledby="travel-booking-detail-price">
                                <h4 id="travel-booking-detail-price">Price</h4>
                                <dl class="travel-detail-list">
                                    @foreach ($selected->priceLines->sortBy('display_order') as $line)
                                        <div><dt>{{ $line->description }}@if ($line->quantity > 1) &times; {{ $line->quantity }}@endif</dt><dd>{{ $money($line->total_minor, $selected) }}</dd></div>
                                    @endforeach
                                    @if ($selected->tax_total_minor > 0)
                                        <div><dt>Tax</dt><dd>{{ $money($selected->tax_total_minor, $selected) }}</dd></div>
                                    @endif
                                    <div><dt><strong>Total</strong></dt><dd><strong>{{ $money($selected->total_minor, $selected) }}</strong></dd></div>
                                    <div><dt>Deposit required</dt><dd>{{ $money($selected->deposit_required_minor, $selected) }}</dd></div>
                                    <div><dt>Received</dt><dd>{{ $money($selected->paid_minor, $selected) }}@if ($selected->refunded_minor > 0) &middot; refunded {{ $money($selected->refunded_minor, $selected) }}@endif</dd></div>
                                    <div><dt>Outstanding</dt><dd>{{ $money(max($selected->total_minor - ($selected->paid_minor - $selected->refunded_minor), 0), $selected) }}</dd></div>
                                </dl>
                            </section>
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-booking-detail-payments">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                                    <h4 id="travel-booking-detail-payments" class="mb-0">Payments</h4>
                                    <div class="travel-admin-row-actions">
                                        @if ($canRecord)
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="openPayment({{ $selected->getKey() }})"><i class="ti ti-cash me-1" aria-hidden="true"></i>Record payment</button>
                                        @endif
                                        @if ($canRefund)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openRefund({{ $selected->getKey() }})"><i class="ti ti-receipt-refund me-1" aria-hidden="true"></i>Record refund</button>
                                        @endif
                                    </div>
                                </div>
                                @if ($selected->payments->isEmpty())
                                    <p class="aureon-muted mb-0">No payments have been recorded.</p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr><th scope="col">Recorded</th><th scope="col">Method</th><th scope="col">Reference</th><th scope="col" class="text-end">Amount</th><th scope="col">State</th><th scope="col" class="text-end">Actions</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($selected->payments as $payment)
                                                    <tr wire:key="payment-{{ $payment->getKey() }}">
                                                        <td>{{ $payment->created_at?->format('d M Y, H:i') }}<small class="d-block aureon-muted">{{ $payment->receiver?->name ?: 'System' }}</small></td>
                                                        <td>{{ $payment->method->label() }}@if ($payment->provider)<small class="d-block aureon-muted">{{ $payment->provider }}</small>@endif</td>
                                                        <td>{{ $payment->reference ?: '—' }}@if ($payment->transaction_identifier)<small class="d-block aureon-muted">{{ $payment->transaction_identifier }}</small>@endif</td>
                                                        <td class="text-end">{{ $money($payment->amount_minor, $selected) }}</td>
                                                        <td>
                                                            <span class="travel-status {{ $payment->status === PaymentRecordStatus::Confirmed ? 'travel-status--active' : ($payment->status === PaymentRecordStatus::Pending ? 'travel-status--review' : 'travel-status--muted') }}">{{ $payment->status->label() }}</span>
                                                            @if ($payment->status === PaymentRecordStatus::Failed && ($payment->safe_metadata['rejection_reason'] ?? null))
                                                                <small class="d-block aureon-muted">{{ $payment->safe_metadata['rejection_reason'] }}</small>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            @if ($payment->status === PaymentRecordStatus::Pending && $canConfirmPayments)
                                                                <div class="travel-admin-row-actions justify-content-end">
                                                                    <button type="button" class="btn btn-sm btn-success" wire:click="confirmPayment({{ $payment->getKey() }})" wire:loading.attr="disabled" wire:target="confirmPayment"><span wire:loading.remove wire:target="confirmPayment">Confirm</span><span wire:loading wire:target="confirmPayment">Confirming…</span></button>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openReject({{ $payment->getKey() }})">Reject</button>
                                                                </div>
                                                            @elseif ($payment->status === PaymentRecordStatus::Pending)
                                                                <small class="aureon-muted">Awaiting confirmation</small>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                                @if ($selected->refunds->isNotEmpty())
                                    <h5 class="fs-13 fw-bold mt-3 mb-2">Refunds</h5>
                                    <dl class="travel-detail-list">
                                        @foreach ($selected->refunds as $refund)
                                            <div><dt>{{ $refund->completed_at?->format('d M Y, H:i') }} &middot; {{ $refund->reference }}</dt><dd>{{ $money($refund->amount_minor, $selected) }} &middot; {{ $refund->reason }}</dd></div>
                                        @endforeach
                                    </dl>
                                @endif
                            </section>
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-booking-detail-history">
                                <h4 id="travel-booking-detail-history">History</h4>
                                <dl class="travel-detail-list">
                                    @foreach ($selected->statusHistory as $entry)
                                        <div><dt>{{ $entry->changed_at->format('d M Y, H:i') }}</dt><dd>{{ $entry->previous_status?->label() ?: 'New' }} &rarr; {{ $entry->new_status->label() }} &middot; {{ $entry->actor?->name ?: Str::headline($entry->source) }}@if ($entry->reason) &middot; {{ $entry->reason }}@endif</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between flex-wrap gap-2">
                        <div class="travel-admin-row-actions">
                            @if ($canManage && $selected->status === BookingStatus::Pending)
                                <button type="button" class="btn btn-success" wire:click="confirmBooking({{ $selected->getKey() }})" wire:loading.attr="disabled" wire:target="confirmBooking"><i class="ti ti-check me-1" aria-hidden="true"></i><span wire:loading.remove wire:target="confirmBooking">Confirm booking</span><span wire:loading wire:target="confirmBooking">Confirming…</span></button>
                            @endif
                            @if ($canManage && $selected->status === BookingStatus::Confirmed && $selected->departure_ends_at_snapshot->isPast())
                                <button type="button" class="btn btn-outline-success" wire:click="completeBooking({{ $selected->getKey() }})" wire:loading.attr="disabled" wire:target="completeBooking"><i class="ti ti-flag-check me-1" aria-hidden="true"></i><span wire:loading.remove wire:target="completeBooking">Mark completed</span><span wire:loading wire:target="completeBooking">Completing…</span></button>
                            @endif
                            @if ($canManage && in_array($selected->status, [BookingStatus::Pending, BookingStatus::Confirmed], true))
                                <button type="button" class="btn btn-outline-danger" wire:click="openCancel({{ $selected->getKey() }})"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancel booking</button>
                            @endif
                        </div>
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'payment' && $selected)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-booking-payment-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#payment-amount')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="recordPayment" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-booking-payment-title" class="modal-title fs-18">Record payment</h3>
                            <p class="aureon-muted mb-0">{{ $selected->booking_number }} &middot; outstanding {{ $money(max($selected->total_minor - ($selected->paid_minor - $selected->refunded_minor), 0), $selected) }}</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close payment form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <p class="aureon-muted">The payment is recorded as evidence and settles the booking only once it is confirmed by someone permitted to confirm payments.</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="payment-method" class="form-label">Method</label>
                                <select id="payment-method" class="form-select @error('payment.method') is-invalid @enderror" wire:model="payment.method">
                                    @foreach (PaymentMethod::cases() as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                @error('payment.method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment-amount" class="form-label">Amount ({{ $selected->currency }})</label>
                                <input id="payment-amount" type="text" inputmode="decimal" class="form-control @error('payment.amount') is-invalid @enderror" wire:model="payment.amount" placeholder="0.00" @error('payment.amount') aria-invalid="true" aria-describedby="payment-amount-error" @enderror>
                                @error('payment.amount')<div class="invalid-feedback" id="payment-amount-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment-reference" class="form-label">Reference <span class="aureon-muted">(optional)</span></label>
                                <input id="payment-reference" type="text" class="form-control @error('payment.reference') is-invalid @enderror" wire:model="payment.reference" maxlength="120">
                                @error('payment.reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment-provider" class="form-label">Provider or bank <span class="aureon-muted">(optional)</span></label>
                                <input id="payment-provider" type="text" class="form-control @error('payment.provider') is-invalid @enderror" wire:model="payment.provider" maxlength="80">
                                @error('payment.provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment-transaction" class="form-label">Transaction identifier <span class="aureon-muted">(optional)</span></label>
                                <input id="payment-transaction" type="text" class="form-control @error('payment.transactionIdentifier') is-invalid @enderror" wire:model="payment.transactionIdentifier" maxlength="160">
                                @error('payment.transactionIdentifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="payment-note" class="form-label">Note <span class="aureon-muted">(optional)</span></label>
                                <input id="payment-note" type="text" class="form-control @error('payment.note') is-invalid @enderror" wire:model="payment.note" maxlength="500">
                                @error('payment.note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="openDetails({{ $selected->getKey() }})">Back</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordPayment">
                            <span wire:loading.remove wire:target="recordPayment">Record payment</span>
                            <span wire:loading wire:target="recordPayment">Recording…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'refund' && $selected)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-booking-refund-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#refund-amount')?.focus())">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="recordRefund" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-booking-refund-title" class="modal-title fs-18">Record refund</h3>
                            <p class="aureon-muted mb-0">{{ $selected->booking_number }} &middot; refundable {{ $money(max($selected->paid_minor - $selected->refunded_minor, 0), $selected) }}</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close refund form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <p class="aureon-muted">Record a refund that has already been paid back to the customer. Cash handed back at a booking desk is written to that shift.</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="refund-payment" class="form-label">Against payment <span class="aureon-muted">(optional)</span></label>
                                <select id="refund-payment" class="form-select @error('refund.paymentId') is-invalid @enderror" wire:model="refund.paymentId">
                                    <option value="">Booking balance</option>
                                    @foreach ($selected->payments->where('status', PaymentRecordStatus::Confirmed) as $payment)
                                        <option value="{{ $payment->getKey() }}">{{ $payment->paid_at?->format('d M Y') }} &middot; {{ $payment->method->label() }} &middot; {{ $money($payment->amount_minor, $selected) }}</option>
                                    @endforeach
                                </select>
                                @error('refund.paymentId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="refund-amount" class="form-label">Amount ({{ $selected->currency }})</label>
                                <input id="refund-amount" type="text" inputmode="decimal" class="form-control @error('refund.amount') is-invalid @enderror" wire:model="refund.amount" placeholder="0.00" @error('refund.amount') aria-invalid="true" aria-describedby="refund-amount-error" @enderror>
                                @error('refund.amount')<div class="invalid-feedback" id="refund-amount-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="refund-reason" class="form-label">Reason</label>
                                <textarea id="refund-reason" rows="2" class="form-control @error('refund.reason') is-invalid @enderror" wire:model="refund.reason" maxlength="1000" @error('refund.reason') aria-invalid="true" aria-describedby="refund-reason-error" @enderror></textarea>
                                @error('refund.reason')<div class="invalid-feedback" id="refund-reason-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="refund-processor" class="form-label">Processor <span class="aureon-muted">(optional)</span></label>
                                <input id="refund-processor" type="text" class="form-control @error('refund.processor') is-invalid @enderror" wire:model="refund.processor" maxlength="80">
                                @error('refund.processor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="refund-transaction" class="form-label">Transaction identifier <span class="aureon-muted">(optional)</span></label>
                                <input id="refund-transaction" type="text" class="form-control @error('refund.transactionIdentifier') is-invalid @enderror" wire:model="refund.transactionIdentifier" maxlength="160">
                                @error('refund.transactionIdentifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="openDetails({{ $selected->getKey() }})">Back</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordRefund">
                            <span wire:loading.remove wire:target="recordRefund">Record refund</span>
                            <span wire:loading wire:target="recordRefund">Recording…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if (in_array($dialog, ['cancel', 'reject'], true) && $selected)
        @php
            $isCancel = $dialog === 'cancel';
            $action = $isCancel ? 'cancelBooking' : 'rejectPayment';
        @endphp
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-booking-reason-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#reason')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="{{ $action }}" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-booking-reason-title" class="modal-title fs-18">{{ $isCancel ? 'Cancel booking' : 'Reject payment' }}</h3>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <p class="aureon-muted">
                            @if ($isCancel)
                                Cancelling {{ $selected->booking_number }} releases its places. Money already received stays on the booking until a refund is recorded.
                            @else
                                The payment stays on record as failed with your reason; the booking's balance is unchanged.
                            @endif
                        </p>
                        <label for="reason" class="form-label">Reason</label>
                        <textarea id="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" wire:model="reason" maxlength="500" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror></textarea>
                        @error('reason')<div class="invalid-feedback" id="reason-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="openDetails({{ $selected->getKey() }})">Back</button>
                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="{{ $action }}">
                            <span wire:loading.remove wire:target="{{ $action }}">{{ $isCancel ? 'Cancel booking' : 'Reject payment' }}</span>
                            <span wire:loading wire:target="{{ $action }}">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
