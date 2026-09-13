<div>
    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    @php($stats = [
        ['label' => 'Configured registers', 'value' => number_format($this->statistics['total']), 'icon' => 'ti-building-store', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Active endpoints', 'value' => number_format($this->statistics['active']), 'icon' => 'ti-circle-check', 'color' => '#198754'],
        ['label' => 'Open sessions', 'value' => number_format($this->statistics['open']), 'icon' => 'ti-cash-register', 'color' => 'var(--aureon-secondary)'],
    ])

    <section class="row" aria-label="Register statistics">
        @foreach ($stats as $stat)
            <div class="col-xl-4 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                        <div><h3>{{ $stat['value'] }}</h3><p>{{ $stat['label'] }}</p></div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="card aureon-panel mb-0" aria-labelledby="register-list-title">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><h3 id="register-list-title" class="card-title mb-1">Register directory</h3><p class="aureon-muted fs-12 mb-0">Stable codes, physical labels, state, and transaction usage</p></div>
            <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2"></i>Add register</button>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7"><label for="register-search" class="form-label">Search registers</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="register-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Name, code, or location"></div></div>
                <div class="col-lg-3"><label for="register-state" class="form-label">State</label><select id="register-state" class="form-select" wire:model.live="state"><option value="all">All registers</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="$set('search', '')"><i class="ti ti-filter-off me-2"></i>Clear</button></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Register</th><th>Location</th><th>Receipt</th><th>Current session</th><th>Usage</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($this->registers as $register)
                        @php($openTill = $register->tillSessions->first())
                        <tr wire:key="register-{{ $register->id }}">
                            <td><span class="d-block fw-semibold">{{ $register->name }}</span><small class="aureon-muted">{{ $register->code }}</small></td>
                            <td>{{ $register->location_label ?: 'Not assigned' }}</td>
                            <td><span class="d-block">{{ $register->receipt_paper_width->label() }}</span><small class="aureon-muted">{{ $register->receipt_print_mode->label() }}</small></td>
                            <td>@if ($openTill)<span class="badge aureon-commerce-badge aureon-commerce-badge--published">Open</span><small class="d-block aureon-muted mt-1">{{ $openTill->opener->display_name }}</small>@else<span class="aureon-muted">Available</span>@endif</td>
                            <td><span class="d-block">{{ number_format($register->orders_count) }} orders</span><small class="aureon-muted">{{ number_format($register->till_sessions_count) }} sessions</small></td>
                            <td><span class="badge aureon-commerce-badge {{ $register->is_active ? 'aureon-commerce-badge--published' : 'aureon-commerce-badge--archived' }}">{{ $register->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end"><div class="d-inline-flex gap-2"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $register->id }})" title="Edit register" aria-label="Edit {{ $register->name }}"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm {{ $register->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" wire:click="toggleActive({{ $register->id }})" wire:confirm="{{ $register->is_active ? 'Deactivate this register?' : 'Activate this register?' }}" title="{{ $register->is_active ? 'Deactivate' : 'Activate' }} register" aria-label="{{ $register->is_active ? 'Deactivate' : 'Activate' }} {{ $register->name }}"><i class="ti ti-{{ $register->is_active ? 'player-pause' : 'player-play' }}"></i></button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5"><i class="ti ti-building-store fs-32 aureon-muted"></i><h4 class="fs-16 mt-2 mb-1">No registers found</h4><p class="aureon-muted mb-0">Create a register or adjust the current filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->registers->hasPages())<div class="card-footer">{{ $this->registers->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="register-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" wire:submit="save">
                <div class="modal-header"><div><h3 id="register-form-title" class="modal-title fs-18">{{ $selectedRegisterId ? 'Edit register' : 'Add register' }}</h3><p class="aureon-muted fs-12 mb-0">Name the physical or logical checkout endpoint</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
                <div class="modal-body"><div class="row g-3">
                    <div class="col-md-7"><label for="register-name" class="form-label">Register name <span class="text-danger">*</span></label><input id="register-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" autofocus>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="register-code" class="form-label">Code <span class="text-danger">*</span></label><input id="register-code" type="text" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" placeholder="FRONT-01">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label for="register-location" class="form-label">Location label</label><input id="register-location" type="text" class="form-control @error('form.locationLabel') is-invalid @enderror" wire:model="form.locationLabel" placeholder="Reception desk">@error('form.locationLabel')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label for="register-description" class="form-label">Description</label><textarea id="register-description" class="form-control @error('form.description') is-invalid @enderror" rows="3" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><hr class="my-1"><h4 class="fs-15 mb-0">Receipt printing</h4></div>
                    <div class="col-md-4"><label for="register-print-driver" class="form-label">Driver</label><select id="register-print-driver" class="form-select @error('form.receiptPrintDriver') is-invalid @enderror" wire:model="form.receiptPrintDriver">@foreach(array_keys((array) config('commerce.pos.receipt_printing.drivers', [])) as $driver)<option value="{{ $driver }}">{{ \Illuminate\Support\Str::headline($driver) }}</option>@endforeach</select>@error('form.receiptPrintDriver')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label for="register-print-mode" class="form-label">Print mode</label><select id="register-print-mode" class="form-select @error('form.receiptPrintMode') is-invalid @enderror" wire:model="form.receiptPrintMode">@foreach(\App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode::cases() as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach</select>@error('form.receiptPrintMode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label for="register-paper-width" class="form-label">Paper width</label><select id="register-paper-width" class="form-select @error('form.receiptPaperWidth') is-invalid @enderror" wire:model="form.receiptPaperWidth">@foreach(\App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth::cases() as $width)<option value="{{ $width->value }}">{{ $width->label() }}</option>@endforeach</select>@error('form.receiptPaperWidth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label for="register-printer-name" class="form-label">Printer label</label><input id="register-printer-name" type="text" class="form-control @error('form.receiptPrinterName') is-invalid @enderror" wire:model="form.receiptPrinterName" placeholder="Front desk receipt printer">@error('form.receiptPrinterName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><div class="form-check form-switch"><input id="register-active" class="form-check-input" type="checkbox" wire:model="form.isActive"><label class="form-check-label" for="register-active">Available for new till sessions</label></div></div>
                </div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">Save register</span><span wire:loading wire:target="save">Saving...</span></button></div>
            </form></div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close register form"></button>
    @endif
</div>
