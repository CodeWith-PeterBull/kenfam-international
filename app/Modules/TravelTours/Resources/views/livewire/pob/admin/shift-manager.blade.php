@use('App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $currency = (string) config('travel-tours.defaults.currency', 'KES');
    $exponent = (int) config('travel-tours.defaults.currency_decimals', 2);
    $variance = $this->statistics['variance_minor'];
@endphp
<section class="travel-shifts" aria-labelledby="shift-list-title">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="row" aria-label="Shift statistics">
        @foreach ([
            ['label' => 'Open shifts', 'value' => number_format($this->statistics['open']), 'icon' => 'clock-dollar'],
            ['label' => 'Closed today', 'value' => number_format($this->statistics['closed_today']), 'icon' => 'circle-check'],
            ['label' => 'Expected open cash', 'value' => MoneyFormatter::format($this->statistics['expected_cash_minor'], $currency, $exponent), 'icon' => 'cash'],
            ['label' => 'Today’s variance', 'value' => MoneyFormatter::format($variance, $currency, $exponent), 'icon' => 'scale'],
        ] as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4 w-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti ti-{{ $stat['icon'] }}" aria-hidden="true"></i></span>
                        <div><strong class="fs-3 d-block">{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    <div class="card aureon-panel mb-0">
        <div class="card-header travel-child-toolbar">
            <div>
                <h3 id="shift-list-title" class="card-title mb-1">Shift history</h3>
                <p>Register and operator ownership, booking activity, and cash reconciliation.</p>
            </div>
            <button type="button" class="btn btn-primary" wire:click="openShiftDialog"><i class="ti ti-lock-open me-2" aria-hidden="true"></i>Open shift</button>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label for="shift-search" class="form-label">Search shifts</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
                        <input id="shift-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Register, operator, or email">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label for="shift-state" class="form-label">Status</label>
                    <select id="shift-state" class="form-select" wire:model.live="state">
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                        <option value="all">All shifts</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="button" class="btn btn-outline-secondary w-100" wire:click="$set('search', '')"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Register</th><th>Operator</th><th>Opened</th><th>Cash position</th><th>Activity</th><th>Status</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->shifts as $shift)
                        @php($money = static fn (int $minor): string => MoneyFormatter::format($minor, $shift->currency, (int) $shift->currency_exponent))
                        <tr wire:key="travel-shift-{{ $shift->id }}">
                            <td>
                                <span class="d-block fw-semibold">{{ $shift->register->name }}</span>
                                <small class="aureon-muted">{{ $shift->register->code }}</small>
                            </td>
                            <td>
                                <span class="d-block">{{ $shift->operator?->display_name }}</span>
                                <small class="aureon-muted">{{ $shift->operator?->email }}</small>
                            </td>
                            <td>
                                <span class="d-block">{{ $shift->opened_at->format('d M Y') }}</span>
                                <small class="aureon-muted">{{ $shift->opened_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <span class="d-block">Expected {{ $money($shift->status === ShiftStatus::Open ? (int) $shift->ledger_cash_minor : (int) $shift->expected_cash_minor) }}</span>
                                @if ($shift->actual_cash_minor !== null)
                                    <small class="aureon-muted">Counted {{ $money((int) $shift->actual_cash_minor) }}</small>
                                @else
                                    <small class="aureon-muted">Float {{ $money((int) $shift->opening_float_minor) }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="d-block">{{ number_format($shift->bookings_count) }} {{ Str::plural('booking', $shift->bookings_count) }}</span>
                                <small class="aureon-muted">{{ number_format($shift->payments_count) }} {{ Str::plural('tender', $shift->payments_count) }}</small>
                            </td>
                            <td>
                                <span class="travel-status {{ $shift->status === ShiftStatus::Open ? 'travel-status--active' : 'travel-status--muted' }}">{{ $shift->status->label() }}</span>
                                @if ($shift->variance_minor !== null)
                                    <small class="d-block mt-1 {{ $shift->variance_minor === 0 ? 'text-success' : 'text-warning' }}">Variance {{ $money((int) $shift->variance_minor) }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($shift->status === ShiftStatus::Open)
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openCloseDialog({{ $shift->id }})"><i class="ti ti-scale me-1" aria-hidden="true"></i>Reconcile</button>
                                @elseif ($shift->status === ShiftStatus::Closed)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openSignOff({{ $shift->id }})"><i class="ti ti-signature me-1" aria-hidden="true"></i>Sign off</button>
                                @else
                                    <span class="aureon-muted fs-12">Signed off {{ $shift->reconciled_at?->format('d M, H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="ti ti-clock-dollar fs-32 aureon-muted" aria-hidden="true"></i>
                                <h4 class="fs-16 mt-2 mb-1">No shifts found</h4>
                                <p class="aureon-muted mb-0">Open a shift or adjust the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->shifts->hasPages())
            <div class="card-footer">{{ $this->shifts->onEachSide(1)->links() }}</div>
        @endif
    </div>

    @if ($dialog === 'open')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-open-shift-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="openShift" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-open-shift-title" class="modal-title fs-18">Open booking shift</h3>
                            <p class="aureon-muted mb-0">Assign one free register to one free desk operator.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close shift form"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="open-register" class="form-label">Register <span class="text-danger">*</span></label>
                                <select id="open-register" class="form-select @error('openForm.registerId') is-invalid @enderror" wire:model.live="openForm.registerId">
                                    <option value="">Select an available register</option>
                                    @foreach ($this->availableRegisters as $register)
                                        <option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>
                                    @endforeach
                                </select>
                                @error('openForm.registerId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="open-operator" class="form-label">Operator <span class="text-danger">*</span></label>
                                <select id="open-operator" class="form-select @error('openForm.operatorId') is-invalid @enderror" wire:model="openForm.operatorId" @disabled($this->availableOperators->isEmpty())>
                                    <option value="">Select an available operator</option>
                                    @foreach ($this->availableOperators as $operator)
                                        <option value="{{ $operator->id }}">{{ $operator->display_name }} ({{ $operator->email }})</option>
                                    @endforeach
                                </select>
                                @error('openForm.operatorId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="opening-float" class="form-label">Opening float ({{ $currency }}) <span class="text-danger">*</span></label>
                                <input id="opening-float" type="text" inputmode="decimal" class="form-control @error('openForm.openingFloat') is-invalid @enderror" wire:model="openForm.openingFloat">
                                @error('openForm.openingFloat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="opening-note" class="form-label">Opening note</label>
                                <textarea id="opening-note" rows="3" class="form-control @error('openForm.note') is-invalid @enderror" wire:model="openForm.note"></textarea>
                                @error('openForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="openShift"><i class="ti ti-lock-open me-2" aria-hidden="true"></i><span wire:loading.remove wire:target="openShift">Open shift</span><span wire:loading wire:target="openShift">Opening...</span></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'close' && $selectedShift)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-close-shift-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="closeShift" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-close-shift-title" class="modal-title fs-18">Reconcile and close</h3>
                            <p class="aureon-muted mb-0">{{ $selectedShift->register->name }} &middot; {{ $selectedShift->operator?->display_name }} &middot; expected cash is recalculated from the ledger on commit.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close reconciliation form"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="counted-cash" class="form-label">Counted cash ({{ $selectedShift->currency }}) <span class="text-danger">*</span></label>
                                <input id="counted-cash" type="text" inputmode="decimal" class="form-control @error('closeForm.countedCash') is-invalid @enderror" wire:model="closeForm.countedCash">
                                @error('closeForm.countedCash')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="closing-note" class="form-label">Closing note</label>
                                <textarea id="closing-note" rows="3" class="form-control @error('closeForm.note') is-invalid @enderror" wire:model="closeForm.note" placeholder="Explain any variance"></textarea>
                                @error('closeForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="closeShift"><i class="ti ti-scale me-2" aria-hidden="true"></i><span wire:loading.remove wire:target="closeShift">Reconcile and close</span><span wire:loading wire:target="closeShift">Closing...</span></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'sign-off' && $selectedShift)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-sign-off-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="signOff" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-sign-off-title" class="modal-title fs-18">Sign off shift</h3>
                            <p class="aureon-muted mb-0">{{ $selectedShift->register->name }} &middot; variance {{ MoneyFormatter::format((int) $selectedShift->variance_minor, $selectedShift->currency, (int) $selectedShift->currency_exponent) }}</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close sign-off form"></button>
                    </div>
                    <div class="modal-body">
                        <label for="sign-off-note" class="form-label">Review note</label>
                        <textarea id="sign-off-note" rows="3" class="form-control @error('signOffNote') is-invalid @enderror" wire:model="signOffNote" placeholder="How the variance was resolved"></textarea>
                        @error('signOffNote')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="signOff"><i class="ti ti-signature me-2" aria-hidden="true"></i><span wire:loading.remove wire:target="signOff">Sign off</span><span wire:loading wire:target="signOff">Saving...</span></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</section>
