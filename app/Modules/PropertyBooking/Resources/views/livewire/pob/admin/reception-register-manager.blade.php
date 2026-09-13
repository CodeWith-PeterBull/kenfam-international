<div>
    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="pb-stat-grid" aria-label="Register statistics">
        @foreach ([
            ['label' => 'Configured registers', 'value' => $this->statistics['total'], 'icon' => 'ti-building-store', 'color' => 'var(--aureon-primary)'],
            ['label' => 'Active endpoints', 'value' => $this->statistics['active'], 'icon' => 'ti-circle-check', 'color' => '#198754'],
            ['label' => 'Open shifts', 'value' => $this->statistics['open'], 'icon' => 'ti-clock-dollar', 'color' => 'var(--aureon-secondary)'],
        ] as $stat)
            <article class="card aureon-panel pb-stat-card" style="--pb-stat-color: {{ $stat['color'] }}">
                <span class="pb-stat-card__icon" aria-hidden="true"><i class="ti {{ $stat['icon'] }}"></i></span>
                <span class="pb-stat-card__copy"><strong>{{ number_format($stat['value']) }}</strong><small>{{ $stat['label'] }}</small></span>
            </article>
        @endforeach
    </section>

    <section class="card aureon-panel mb-0" aria-labelledby="register-list-title">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><h3 id="register-list-title" class="card-title mb-1">Register directory</h3><p class="aureon-muted fs-12 mb-0">Property scope, stable codes, printer behavior, and shift usage</p></div>
            <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2"></i>Add register</button>
        </div>
        <div class="card-body border-bottom"><div class="row g-3 align-items-end">
            <div class="col-lg-7"><label for="register-search" class="form-label">Search registers</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="register-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Property, name, code, or location"></div></div>
            <div class="col-lg-3"><label for="register-state" class="form-label">State</label><select id="register-state" class="form-select" wire:model.live="state"><option value="all">All registers</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="$set('search', '')"><i class="ti ti-filter-off me-2"></i>Clear</button></div>
        </div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Register</th><th>Property</th><th>Receipt</th><th>Current shift</th><th>Usage</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse ($this->registers as $register)
                @php($openShift = $register->shifts->first())
                <tr wire:key="reception-register-{{ $register->id }}">
                    <td><span class="d-block fw-semibold">{{ $register->name }}</span><small class="aureon-muted">{{ $register->code }}@if($register->location_label) &middot; {{ $register->location_label }}@endif</small></td>
                    <td>{{ $register->property->name }}</td>
                    <td><span class="d-block">{{ $register->receipt_paper_width->label() }}</span><small class="aureon-muted">{{ $register->receipt_print_mode->label() }}</small></td>
                    <td>@if($openShift)<span class="pb-badge pb-badge--success">Open</span><small class="d-block aureon-muted mt-1">{{ $openShift->receptionist->display_name }}</small>@else<span class="aureon-muted">Available</span>@endif</td>
                    <td><span class="d-block">{{ number_format($register->bookings_count) }} bookings</span><small class="aureon-muted">{{ number_format($register->shifts_count) }} shifts</small></td>
                    <td><span class="pb-badge {{ $register->is_active ? 'pb-badge--success' : 'pb-badge--neutral' }}">{{ $register->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end"><div class="d-inline-flex gap-2"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $register->id }})" title="Edit register" aria-label="Edit {{ $register->name }}"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm {{ $register->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" wire:click="toggleActive({{ $register->id }})" wire:confirm="{{ $register->is_active ? 'Deactivate this reception register?' : 'Activate this reception register?' }}" title="{{ $register->is_active ? 'Deactivate' : 'Activate' }} register"><i class="ti ti-{{ $register->is_active ? 'player-pause' : 'player-play' }}"></i></button></div></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5"><i class="ti ti-building-store fs-32 aureon-muted"></i><h4 class="fs-16 mt-2 mb-1">No reception registers found</h4><p class="aureon-muted mb-0">Create an endpoint or adjust the current filters.</p></td></tr>
            @endforelse
        </tbody></table></div>
        @if($this->registers->hasPages())<div class="card-footer">{{ $this->registers->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reception-register-form-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" wire:submit="save">
            <div class="modal-header"><div><h3 id="reception-register-form-title" class="modal-title fs-18">{{ $selectedRegisterId ? 'Edit reception register' : 'Add reception register' }}</h3><p class="aureon-muted fs-12 mb-0">Configure one physical or logical booking endpoint</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label for="register-property" class="form-label">Property <span class="text-danger">*</span></label><select id="register-property" class="form-select @error('form.propertyId') is-invalid @enderror" wire:model="form.propertyId" @disabled($selectedRegisterId)>@foreach($this->properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select>@error('form.propertyId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-7"><label for="register-name" class="form-label">Register name <span class="text-danger">*</span></label><input id="register-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" autofocus>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-5"><label for="register-code" class="form-label">Code <span class="text-danger">*</span></label><input id="register-code" type="text" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" placeholder="MAIN-DESK">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="register-location" class="form-label">Location label</label><input id="register-location" type="text" class="form-control @error('form.locationLabel') is-invalid @enderror" wire:model="form.locationLabel" placeholder="Main lobby">@error('form.locationLabel')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="register-description" class="form-label">Description</label><textarea id="register-description" rows="3" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><hr class="my-1"><h4 class="fs-15 mb-0">Receipt printing</h4></div>
                <div class="col-md-4"><label for="register-driver" class="form-label">Driver</label><select id="register-driver" class="form-select @error('form.receiptPrintDriver') is-invalid @enderror" wire:model="form.receiptPrintDriver">@foreach(array_keys((array) config('property-booking.pob.receipt_printing.drivers', [])) as $driver)<option value="{{ $driver }}">{{ \Illuminate\Support\Str::headline($driver) }}</option>@endforeach</select>@error('form.receiptPrintDriver')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label for="register-mode" class="form-label">Print mode</label><select id="register-mode" class="form-select @error('form.receiptPrintMode') is-invalid @enderror" wire:model="form.receiptPrintMode">@foreach(\App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode::cases() as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach</select>@error('form.receiptPrintMode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label for="register-width" class="form-label">Paper width</label><select id="register-width" class="form-select @error('form.receiptPaperWidth') is-invalid @enderror" wire:model="form.receiptPaperWidth">@foreach(\App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth::cases() as $width)<option value="{{ $width->value }}">{{ $width->label() }}</option>@endforeach</select>@error('form.receiptPaperWidth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label for="register-printer" class="form-label">Printer label</label><input id="register-printer" type="text" class="form-control @error('form.receiptPrinterName') is-invalid @enderror" wire:model="form.receiptPrinterName" placeholder="Main reception thermal printer">@error('form.receiptPrinterName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><div class="form-check form-switch"><input id="register-active" class="form-check-input" type="checkbox" wire:model="form.isActive"><label class="form-check-label" for="register-active">Available for new reception shifts</label></div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">Save register</span><span wire:loading wire:target="save">Saving...</span></button></div>
        </form></div></div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close register form"></button>
    @endif
</div>
