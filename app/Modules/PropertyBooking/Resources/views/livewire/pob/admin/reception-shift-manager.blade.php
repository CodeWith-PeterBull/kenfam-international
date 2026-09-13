<div>
    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    @php($variance = $this->statistics['variance_minor'])
    <section class="pb-stat-grid" aria-label="Reception shift statistics">
        @foreach ([
            ['label' => 'Open shifts', 'value' => number_format($this->statistics['open']), 'icon' => 'ti-clock-dollar', 'color' => 'var(--aureon-primary)'],
            ['label' => 'Closed today', 'value' => number_format($this->statistics['closed_today']), 'icon' => 'ti-circle-check', 'color' => '#198754'],
            ['label' => 'Expected open cash', 'value' => \App\Modules\PropertyBooking\Support\MoneyFormatter::format($this->statistics['expected_cash_minor']), 'icon' => 'ti-cash', 'color' => 'var(--aureon-secondary)'],
            ['label' => "Today's variance", 'value' => ($variance < 0 ? '-' : '').\App\Modules\PropertyBooking\Support\MoneyFormatter::format(abs($variance)), 'icon' => 'ti-scale', 'color' => $variance === 0 ? '#198754' : '#b7791f'],
        ] as $stat)
            <article class="card aureon-panel pb-stat-card" style="--pb-stat-color: {{ $stat['color'] }}"><span class="pb-stat-card__icon" aria-hidden="true"><i class="ti {{ $stat['icon'] }}"></i></span><span class="pb-stat-card__copy"><strong>{{ $stat['value'] }}</strong><small>{{ $stat['label'] }}</small></span></article>
        @endforeach
    </section>

    <section class="card aureon-panel mb-0" aria-labelledby="shift-list-title">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h3 id="shift-list-title" class="card-title mb-1">Reception shift history</h3><p class="aureon-muted fs-12 mb-0">Register and receptionist ownership, booking activity, and cash reconciliation</p></div><button type="button" class="btn btn-primary" wire:click="openShiftDialog"><i class="ti ti-lock-open me-2"></i>Open shift</button></div>
        <div class="card-body border-bottom"><div class="row g-3 align-items-end">
            <div class="col-lg-7"><label for="shift-search" class="form-label">Search shifts</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="shift-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Property, register, receptionist, or email"></div></div>
            <div class="col-lg-3"><label for="shift-state" class="form-label">Status</label><select id="shift-state" class="form-select" wire:model.live="state"><option value="open">Open</option><option value="closed">Closed</option><option value="all">All shifts</option></select></div>
            <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="$set('search', '')"><i class="ti ti-filter-off me-2"></i>Clear</button></div>
        </div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Register</th><th>Receptionist</th><th>Opened</th><th>Cash position</th><th>Activity</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
            @forelse($this->shifts as $shift)
                <tr wire:key="reception-shift-{{ $shift->id }}">
                    <td><span class="d-block fw-semibold">{{ $shift->register->name }}</span><small class="aureon-muted">{{ $shift->property->name }} &middot; {{ $shift->register->code }}</small></td>
                    <td><span class="d-block">{{ $shift->receptionist->display_name }}</span><small class="aureon-muted">{{ $shift->receptionist->email }}</small></td>
                    <td><span class="d-block">{{ $shift->opened_at->format('d M Y') }}</span><small class="aureon-muted">{{ $shift->opened_at->format('H:i') }}</small></td>
                    <td><span class="d-block">Expected {{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($shift->expected_cash_minor, $shift->currency) }}</span>@if($shift->counted_cash_minor !== null)<small class="aureon-muted">Counted {{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($shift->counted_cash_minor, $shift->currency) }}</small>@else<small class="aureon-muted">Float {{ \App\Modules\PropertyBooking\Support\MoneyFormatter::format($shift->opening_float_minor, $shift->currency) }}</small>@endif</td>
                    <td><span class="d-block">{{ number_format($shift->bookings_count) }} bookings</span><small class="aureon-muted">{{ number_format($shift->payments_count) }} tenders</small></td>
                    <td><span class="pb-badge {{ $shift->isOpen() ? 'pb-badge--success' : 'pb-badge--neutral' }}">{{ $shift->status->label() }}</span>@if($shift->variance_minor !== null)<small class="d-block mt-1 {{ $shift->variance_minor === 0 ? 'text-success' : 'text-warning' }}">Variance {{ ($shift->variance_minor < 0 ? '-' : '').\App\Modules\PropertyBooking\Support\MoneyFormatter::format(abs($shift->variance_minor), $shift->currency) }}</small>@endif</td>
                    <td class="text-end">@if($shift->isOpen())<button type="button" class="btn btn-sm btn-outline-primary" wire:click="openCloseDialog({{ $shift->id }})"><i class="ti ti-scale me-1"></i>Reconcile</button>@else<span class="aureon-muted fs-12">{{ $shift->closed_at?->format('d M, H:i') }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5"><i class="ti ti-clock-dollar fs-32 aureon-muted"></i><h4 class="fs-16 mt-2 mb-1">No reception shifts found</h4><p class="aureon-muted mb-0">Open a shift or adjust the current filters.</p></td></tr>
            @endforelse
        </tbody></table></div>
        @if($this->shifts->hasPages())<div class="card-footer">{{ $this->shifts->onEachSide(1)->links() }}</div>@endif
    </section>

    @if($dialog === 'open')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="open-shift-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" wire:submit="openShift">
            <div class="modal-header"><div><h3 id="open-shift-title" class="modal-title fs-18">Open reception shift</h3><p class="aureon-muted fs-12 mb-0">Assign one available property desk to one receptionist</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label for="open-register" class="form-label">Reception register <span class="text-danger">*</span></label><select id="open-register" class="form-select @error('openForm.registerId') is-invalid @enderror" wire:model.live="openForm.registerId"><option value="">Select an available register</option>@foreach($this->availableRegisters as $register)<option value="{{ $register->id }}">{{ $register->property->name }} - {{ $register->name }} ({{ $register->code }})</option>@endforeach</select>@error('openForm.registerId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="open-receptionist" class="form-label">Receptionist <span class="text-danger">*</span></label><select id="open-receptionist" class="form-select @error('openForm.receptionistId') is-invalid @enderror" wire:model="openForm.receptionistId" @disabled($this->availableReceptionists->isEmpty())><option value="">Select an available receptionist</option>@foreach($this->availableReceptionists as $receptionist)<option value="{{ $receptionist->id }}">{{ $receptionist->display_name }} ({{ $receptionist->email }})</option>@endforeach</select>@error('openForm.receptionistId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="opening-float" class="form-label">Opening float ({{ config('property-booking.defaults.currency') }}) <span class="text-danger">*</span></label><input id="opening-float" type="text" inputmode="decimal" class="form-control @error('openForm.openingFloat') is-invalid @enderror" wire:model="openForm.openingFloat">@error('openForm.openingFloat')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="opening-note" class="form-label">Opening note</label><textarea id="opening-note" rows="3" class="form-control @error('openForm.note') is-invalid @enderror" wire:model="openForm.note"></textarea>@error('openForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="openShift"><i class="ti ti-lock-open me-2"></i><span wire:loading.remove wire:target="openShift">Open shift</span><span wire:loading wire:target="openShift">Opening...</span></button></div>
        </form></div></div><button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close shift form"></button>
    @endif

    @if($dialog === 'close')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="close-shift-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" wire:submit="closeShift">
            <div class="modal-header"><div><h3 id="close-shift-title" class="modal-title fs-18">Reconcile and close</h3><p class="aureon-muted fs-12 mb-0">Expected cash is recalculated from completed cash tenders on commit</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label for="counted-cash" class="form-label">Counted cash ({{ config('property-booking.defaults.currency') }}) <span class="text-danger">*</span></label><input id="counted-cash" type="text" inputmode="decimal" class="form-control @error('closeForm.countedCash') is-invalid @enderror" wire:model="closeForm.countedCash">@error('closeForm.countedCash')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="closing-note" class="form-label">Closing note</label><textarea id="closing-note" rows="3" class="form-control @error('closeForm.note') is-invalid @enderror" wire:model="closeForm.note" placeholder="Explain any variance"></textarea>@error('closeForm.note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="closeShift"><i class="ti ti-scale me-2"></i><span wire:loading.remove wire:target="closeShift">Reconcile and close</span><span wire:loading wire:target="closeShift">Closing...</span></button></div>
        </form></div></div><button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close reconciliation form"></button>
    @endif
</div>
