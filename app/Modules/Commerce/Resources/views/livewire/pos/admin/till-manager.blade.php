<div>
    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    @php($stats = [
        ['label' => 'Open sessions', 'value' => number_format($this->statistics['open']), 'icon' => 'ti-cash-register', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Closed today', 'value' => number_format($this->statistics['closed_today']), 'icon' => 'ti-circle-check', 'color' => 'var(--aureon-secondary)'],
        ['label' => 'Expected open cash', 'value' => \App\Modules\Commerce\Support\MoneyFormatter::format($this->statistics['expected_cash_minor']), 'icon' => 'ti-cash', 'color' => 'var(--aureon-accent)'],
        ['label' => "Today's variance", 'value' => ($this->statistics['variance_minor'] < 0 ? '-' : '') . \App\Modules\Commerce\Support\MoneyFormatter::format(abs($this->statistics['variance_minor'])), 'icon' => 'ti-scale', 'color' => $this->statistics['variance_minor'] === 0 ? '#198754' : '#b7791f'],
    ])

    <section class="row" aria-label="Till statistics">
        @foreach ($stats as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                        <div><h3>{{ $stat['value'] }}</h3><p>{{ $stat['label'] }}</p></div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="card aureon-panel mb-0" aria-labelledby="till-list-title">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h3 id="till-list-title" class="card-title mb-1">Till history</h3><p class="aureon-muted fs-12 mb-0">Cashier ownership, expected cash, counts, and variance</p></div><button type="button" class="btn btn-primary" wire:click="openTillDialog"><i class="ti ti-lock-open me-2"></i>Open till</button></div>
        <div class="card-body border-bottom"><div class="row g-3 align-items-end">
            <div class="col-lg-7"><label for="till-search" class="form-label">Search sessions</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="till-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Register, code, cashier, or email"></div></div>
            <div class="col-lg-3"><label for="till-state" class="form-label">Status</label><select id="till-state" class="form-select" wire:model.live="state"><option value="open">Open</option><option value="closed">Closed</option><option value="all">All sessions</option></select></div>
            <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="$set('search', '')"><i class="ti ti-filter-off me-2"></i>Clear</button></div>
        </div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Register</th><th>Cashier</th><th>Opened</th><th>Cash position</th><th>Activity</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
            @forelse ($this->sessions as $till)
                <tr wire:key="till-{{ $till->id }}">
                    <td><span class="d-block fw-semibold">{{ $till->register->name }}</span><small class="aureon-muted">{{ $till->register->code }}</small></td>
                    <td><span class="d-block">{{ $till->opener->display_name }}</span><small class="aureon-muted">{{ $till->opener->email }}</small></td>
                    <td><span class="d-block">{{ $till->opened_at->format('d M Y') }}</span><small class="aureon-muted">{{ $till->opened_at->format('H:i') }}</small></td>
                    <td><span class="d-block">Expected {{ \App\Modules\Commerce\Support\MoneyFormatter::format($till->expected_cash_minor) }}</span>@if ($till->counted_cash_minor !== null)<small class="aureon-muted">Counted {{ \App\Modules\Commerce\Support\MoneyFormatter::format($till->counted_cash_minor) }}</small>@else<small class="aureon-muted">Float {{ \App\Modules\Commerce\Support\MoneyFormatter::format($till->opening_float_minor) }}</small>@endif</td>
                    <td><span class="d-block">{{ number_format($till->orders_count) }} orders</span><small class="aureon-muted">{{ number_format($till->payments_count) }} tenders</small></td>
                    <td><span class="badge aureon-commerce-badge {{ $till->isOpen() ? 'aureon-commerce-badge--published' : 'aureon-commerce-badge--neutral' }}">{{ $till->status->label() }}</span>@if ($till->variance_minor !== null)<small class="d-block mt-1 {{ $till->variance_minor === 0 ? 'text-success' : 'text-warning' }}">Variance {{ ($till->variance_minor < 0 ? '-' : '') . \App\Modules\Commerce\Support\MoneyFormatter::format(abs($till->variance_minor)) }}</small>@endif</td>
                    <td class="text-end">@if ($till->isOpen())<button type="button" class="btn btn-sm btn-outline-primary" wire:click="openCloseDialog({{ $till->id }})"><i class="ti ti-lock me-1"></i>Reconcile</button>@else<span class="aureon-muted fs-12">{{ $till->closed_at?->format('d M, H:i') }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5"><i class="ti ti-cash-register fs-32 aureon-muted"></i><h4 class="fs-16 mt-2 mb-1">No till sessions found</h4><p class="aureon-muted mb-0">Open a till or adjust the current filters.</p></td></tr>
            @endforelse
        </tbody></table></div>
        @if ($this->sessions->hasPages())<div class="card-footer">{{ $this->sessions->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'open')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="open-till-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" wire:submit="openTill">
            <div class="modal-header"><div><h3 id="open-till-title" class="modal-title fs-18">Open till session</h3><p class="aureon-muted fs-12 mb-0">Assign one available register to one authorized cashier</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label for="open-register" class="form-label">Register <span class="text-danger">*</span></label><select id="open-register" class="form-select @error('openForm.registerId') is-invalid @enderror" wire:model="openForm.registerId"><option value="">Select an available register</option>@foreach ($this->availableRegisters as $register)<option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>@endforeach</select>@error('openForm.registerId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="open-cashier" class="form-label">Cashier <span class="text-danger">*</span></label><select id="open-cashier" class="form-select @error('openForm.cashierId') is-invalid @enderror" wire:model="openForm.cashierId"><option value="">Select an available cashier</option>@foreach ($this->availableCashiers as $cashier)<option value="{{ $cashier->id }}">{{ $cashier->display_name }} - {{ \App\Modules\Commerce\Support\CommerceRole::operatorLabel($cashier) }} ({{ $cashier->email }})</option>@endforeach</select>@error('openForm.cashierId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="opening-float" class="form-label">Opening float ({{ config('commerce.currency.code') }}) <span class="text-danger">*</span></label><input id="opening-float" type="text" inputmode="decimal" class="form-control @error('openForm.openingFloat') is-invalid @enderror" wire:model="openForm.openingFloat">@error('openForm.openingFloat')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="opening-note" class="form-label">Opening note</label><textarea id="opening-note" rows="3" class="form-control @error('openForm.note') is-invalid @enderror" wire:model="openForm.note"></textarea>@error('openForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="openTill"><i class="ti ti-lock-open me-2"></i><span wire:loading.remove wire:target="openTill">Open till</span><span wire:loading wire:target="openTill">Opening...</span></button></div>
        </form></div></div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close till form"></button>
    @endif

    @if ($dialog === 'close')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="close-till-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" wire:submit="closeTill">
            <div class="modal-header"><div><h3 id="close-till-title" class="modal-title fs-18">Reconcile and close</h3><p class="aureon-muted fs-12 mb-0">Enter the physical cash count; expected cash is recalculated on commit</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label for="counted-cash" class="form-label">Counted cash ({{ config('commerce.currency.code') }}) <span class="text-danger">*</span></label><input id="counted-cash" type="text" inputmode="decimal" class="form-control @error('closeForm.countedCash') is-invalid @enderror" wire:model="closeForm.countedCash">@error('closeForm.countedCash')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="closing-note" class="form-label">Closing note</label><textarea id="closing-note" rows="3" class="form-control @error('closeForm.note') is-invalid @enderror" wire:model="closeForm.note" placeholder="Explain any variance"></textarea>@error('closeForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="closeTill"><i class="ti ti-scale me-2"></i><span wire:loading.remove wire:target="closeTill">Reconcile and close</span><span wire:loading wire:target="closeTill">Closing...</span></button></div>
        </form></div></div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close reconciliation form"></button>
    @endif
</div>
