@use('App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus')
@use('App\Modules\TravelTours\PointOfBooking\Models\BookingRegister')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $registers = $this->registers;
    $shifts = $this->shifts;
    $canManage = auth()->user()->can('create', BookingRegister::class);
    $money = static fn (?int $minor, $shift): string => $minor === null ? '—' : MoneyFormatter::format($minor, $shift->currency, (int) $shift->currency_exponent);
    $threshold = (int) config('travel-tours.pob.variance_threshold_minor', 10000);
@endphp

<div id="travel-shift-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($dialog === '')
        @error('management')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endif

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-registers-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-registers-title" class="card-title mb-1">Registers</h3>
                <p class="aureon-muted mb-0">Desks an operator can open a shift on; one open shift per register</p>
            </div>
            @if ($canManage)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add register</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr><th scope="col">Register</th><th scope="col">Receipts</th><th scope="col">Open shift</th><th scope="col">State</th><th scope="col" class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($registers as $register)
                        <tr wire:key="register-{{ $register->getKey() }}">
                            <td><strong class="d-block">{{ $register->name }}</strong><small class="aureon-muted">{{ $register->code }}@if ($register->location) &middot; {{ $register->location }}@endif</small></td>
                            <td><small>{{ $register->receipt_paper_width_mm }} mm &middot; {{ $register->automatic_receipt_print ? 'prints automatically' : 'manual print' }}</small></td>
                            <td>{{ $register->shifts->first()?->operator?->name ?: '—' }}</td>
                            <td><span class="badge {{ $register->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $register->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end">
                                @if ($canManage)
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" wire:click="openEdit({{ $register->getKey() }})" title="Edit {{ $register->name }}" aria-label="Edit {{ $register->name }}"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 aureon-muted">No registers yet. Add one so operators can open a shift.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-shifts-title">
        <div class="card-header">
            <h3 id="travel-shifts-title" class="card-title mb-1">Shifts</h3>
            <p class="aureon-muted mb-0">Expected cash is the float plus cash payments and cash-in, less cash refunds and cash-out; variances above {{ MoneyFormatter::format($threshold, (string) config('travel-tours.defaults.currency', 'KES')) }} are flagged</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr><th scope="col">Shift</th><th scope="col">Operator</th><th scope="col" class="text-center">Bookings</th><th scope="col" class="text-end">Expected</th><th scope="col" class="text-end">Counted</th><th scope="col" class="text-end">Variance</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr wire:key="shift-{{ $shift->getKey() }}">
                            <td><strong class="d-block">{{ $shift->register->name }}</strong><small class="aureon-muted">{{ $shift->opened_at->format('d M Y, H:i') }}@if ($shift->closed_at) to {{ $shift->closed_at->format('H:i') }}@endif</small></td>
                            <td>{{ $shift->operator?->name }}</td>
                            <td class="text-center">{{ $shift->bookings_count }}</td>
                            <td class="text-end">{{ $money((int) $shift->expected_cash_minor, $shift) }}</td>
                            <td class="text-end">{{ $money($shift->actual_cash_minor, $shift) }}</td>
                            <td class="text-end {{ $shift->variance_minor !== null && abs((int) $shift->variance_minor) > $threshold ? 'text-danger fw-semibold' : '' }}">{{ $money($shift->variance_minor, $shift) }}</td>
                            <td><span class="badge {{ $shift->status === ShiftStatus::Open ? 'text-bg-warning' : ($shift->status === ShiftStatus::Reconciled ? 'text-bg-success' : 'text-bg-light') }}">{{ $shift->status->label() }}</span>@if ($shift->reconciler)<small class="d-block aureon-muted">by {{ $shift->reconciler->name }}</small>@endif</td>
                            <td class="text-end">
                                @if ($canManage && $shift->status === ShiftStatus::Closed)
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="openReconcile({{ $shift->getKey() }})">Reconcile</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 aureon-muted">No shifts have been opened.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($shifts->hasPages())
            <div class="card-footer">{{ $shifts->links() }}</div>
        @endif
    </section>

    @if ($dialog === 'register')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-register-form-title" wire:keydown.escape.window="closeDialog" x-data x-init="$nextTick(() => $el.querySelector('#register-code')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="saveRegister" novalidate>
                    <div class="modal-header"><h3 id="travel-register-form-title" class="modal-title fs-18">{{ $registerId ? 'Edit register' : 'Add register' }}</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label for="register-code" class="form-label">Code</label>
                                <input id="register-code" type="text" class="form-control text-uppercase @error('code') is-invalid @enderror" wire:model="code" maxlength="60">
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-7">
                                <label for="register-name" class="form-label">Name</label>
                                <input id="register-name" type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" maxlength="160">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-7">
                                <label for="register-location" class="form-label">Location <span class="aureon-muted">(optional)</span></label>
                                <input id="register-location" type="text" class="form-control @error('location') is-invalid @enderror" wire:model="location" maxlength="160">
                                @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-5">
                                <label for="register-paper" class="form-label">Receipt paper</label>
                                <select id="register-paper" class="form-select" wire:model="paperWidth"><option value="80">80 mm</option><option value="58">58 mm</option></select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-4">
                                <div class="form-check form-switch"><input id="register-auto-print" class="form-check-input" type="checkbox" role="switch" wire:model="automaticPrint"><label class="form-check-label" for="register-auto-print">Print receipts automatically</label></div>
                                <div class="form-check form-switch"><input id="register-active" class="form-check-input" type="checkbox" role="switch" wire:model="isActive"><label class="form-check-label" for="register-active">Active</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveRegister">Save register</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'reconcile' && $selectedShift)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-shift-reconcile-title" wire:keydown.escape.window="closeDialog" x-data x-init="$nextTick(() => $el.querySelector('#reconcile-notes')?.focus())">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="reconcile" novalidate>
                    <div class="modal-header"><h3 id="travel-shift-reconcile-title" class="modal-title fs-18">Reconcile shift</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <dl class="travel-detail-list mb-3">
                            <div><dt>Register</dt><dd>{{ $selectedShift->register->name }} &middot; {{ $selectedShift->operator?->name }}</dd></div>
                            <div><dt>Expected</dt><dd>{{ $money((int) $selectedShift->expected_cash_minor, $selectedShift) }}</dd></div>
                            <div><dt>Counted</dt><dd>{{ $money($selectedShift->actual_cash_minor, $selectedShift) }}</dd></div>
                            <div><dt>Variance</dt><dd>{{ $money($selectedShift->variance_minor, $selectedShift) }}</dd></div>
                            @if ($selectedShift->closing_notes)<div><dt>Operator notes</dt><dd>{{ $selectedShift->closing_notes }}</dd></div>@endif
                        </dl>
                        <label for="reconcile-notes" class="form-label">Reconciliation notes <span class="aureon-muted">(optional)</span></label>
                        <textarea id="reconcile-notes" rows="3" class="form-control @error('notes') is-invalid @enderror" wire:model="notes" maxlength="1000"></textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-success" wire:loading.attr="disabled" wire:target="reconcile">Mark reconciled</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
