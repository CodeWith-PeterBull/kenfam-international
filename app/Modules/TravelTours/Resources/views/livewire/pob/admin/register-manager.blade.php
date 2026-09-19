@use('App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPaperWidth')
@use('App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPrintMode')
<section class="travel-registers" aria-labelledby="register-list-title">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="row" aria-label="Register statistics">
        @foreach ([
            ['label' => 'Configured registers', 'value' => $this->statistics['total'], 'icon' => 'cash-register'],
            ['label' => 'Active registers', 'value' => $this->statistics['active'], 'icon' => 'circle-check'],
            ['label' => 'Open shifts', 'value' => $this->statistics['open'], 'icon' => 'clock-dollar'],
        ] as $stat)
            <div class="col-xl-4 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4 w-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti ti-{{ $stat['icon'] }}" aria-hidden="true"></i></span>
                        <div><strong class="fs-3 d-block">{{ number_format($stat['value']) }}</strong><span>{{ $stat['label'] }}</span></div>
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    <div class="card aureon-panel mb-0">
        <div class="card-header travel-child-toolbar">
            <div>
                <h3 id="register-list-title" class="card-title mb-1">Register directory</h3>
                <p>Stable codes, printer behaviour, and shift usage.</p>
            </div>
            <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add register</button>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label for="register-search" class="form-label">Search registers</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
                        <input id="register-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Name, code, or location">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label for="register-state" class="form-label">State</label>
                    <select id="register-state" class="form-select" wire:model.live="state">
                        <option value="all">All registers</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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
                    <tr><th>Register</th><th>Receipt</th><th>Current shift</th><th>Usage</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->registers as $register)
                        @php($openShift = $register->shifts->first())
                        <tr wire:key="travel-register-{{ $register->id }}">
                            <td>
                                <span class="d-block fw-semibold">{{ $register->name }}</span>
                                <small class="aureon-muted">{{ $register->code }}@if ($register->location) &middot; {{ $register->location }}@endif</small>
                            </td>
                            <td>
                                <span class="d-block">{{ ReceiptPaperWidth::tryFrom((int) $register->receipt_paper_width_mm)?->label() ?? $register->receipt_paper_width_mm.' mm' }}</span>
                                <small class="aureon-muted">{{ ReceiptPrintMode::fromAutomatic((bool) $register->automatic_receipt_print)->label() }}@if ($register->receipt_printer_name) &middot; {{ $register->receipt_printer_name }}@endif</small>
                            </td>
                            <td>
                                @if ($openShift)
                                    <span class="travel-status travel-status--active">Open</span>
                                    <small class="d-block aureon-muted mt-1">{{ $openShift->operator?->display_name }}</small>
                                @else
                                    <span class="aureon-muted">Available</span>
                                @endif
                            </td>
                            <td>
                                <span class="d-block">{{ number_format($register->bookings_count) }} {{ Str::plural('booking', $register->bookings_count) }}</span>
                                <small class="aureon-muted">{{ number_format($register->shifts_count) }} {{ Str::plural('shift', $register->shifts_count) }}</small>
                            </td>
                            <td><span class="travel-status {{ $register->is_active ? 'travel-status--active' : 'travel-status--muted' }}">{{ $register->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $register->id }})" title="Edit register" aria-label="Edit {{ $register->name }}"><i class="ti ti-edit" aria-hidden="true"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm {{ $register->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" wire:click="toggleActive({{ $register->id }})" wire:confirm="{{ $register->is_active ? 'Deactivate this register?' : 'Activate this register?' }}" title="{{ $register->is_active ? 'Deactivate' : 'Activate' }} register" aria-label="{{ $register->is_active ? 'Deactivate' : 'Activate' }} {{ $register->name }}"><i class="ti ti-{{ $register->is_active ? 'player-pause' : 'player-play' }}" aria-hidden="true"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="ti ti-cash-register fs-32 aureon-muted" aria-hidden="true"></i>
                                <h4 class="fs-16 mt-2 mb-1">No registers found</h4>
                                <p class="aureon-muted mb-0">Add a register or adjust the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->registers->hasPages())
            <div class="card-footer">{{ $this->registers->onEachSide(1)->links() }}</div>
        @endif
    </div>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-register-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-register-form-title" class="modal-title fs-18">{{ $selectedRegisterId ? 'Edit register' : 'Add register' }}</h3>
                            <p class="aureon-muted mb-0">One physical or logical booking-desk endpoint.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close register form"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="register-name" class="form-label">Register name <span class="text-danger">*</span></label>
                                <input id="register-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" autofocus>
                                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label for="register-code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input id="register-code" type="text" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" placeholder="MAIN-DESK">
                                @error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="register-location" class="form-label">Location</label>
                                <input id="register-location" type="text" class="form-control @error('form.location') is-invalid @enderror" wire:model="form.location" placeholder="Head office, ground floor">
                                @error('form.location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12"><hr class="my-1"><h4 class="fs-15 mb-0">Receipt printing</h4></div>
                            <div class="col-md-4">
                                <label for="register-driver" class="form-label">Driver</label>
                                <select id="register-driver" class="form-select @error('form.receiptPrintDriver') is-invalid @enderror" wire:model="form.receiptPrintDriver">
                                    @foreach (array_keys((array) config('travel-tours.pob.receipt_printing.drivers', [])) as $driver)
                                        <option value="{{ $driver }}">{{ Str::headline($driver) }}</option>
                                    @endforeach
                                </select>
                                @error('form.receiptPrintDriver')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="register-mode" class="form-label">Print mode</label>
                                <select id="register-mode" class="form-select @error('form.receiptPrintMode') is-invalid @enderror" wire:model="form.receiptPrintMode">
                                    @foreach (ReceiptPrintMode::cases() as $mode)
                                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                    @endforeach
                                </select>
                                @error('form.receiptPrintMode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="register-width" class="form-label">Paper width</label>
                                <select id="register-width" class="form-select @error('form.receiptPaperWidth') is-invalid @enderror" wire:model="form.receiptPaperWidth">
                                    @foreach (ReceiptPaperWidth::cases() as $width)
                                        <option value="{{ $width->value }}">{{ $width->label() }}</option>
                                    @endforeach
                                </select>
                                @error('form.receiptPaperWidth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="register-printer" class="form-label">Printer label</label>
                                <input id="register-printer" type="text" class="form-control @error('form.receiptPrinterName') is-invalid @enderror" wire:model="form.receiptPrinterName" placeholder="Front desk thermal printer">
                                @error('form.receiptPrinterName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input id="register-active" class="form-check-input" type="checkbox" wire:model="form.isActive">
                                    <label class="form-check-label" for="register-active">Available for new shifts</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="ti ti-device-floppy me-2" aria-hidden="true"></i><span wire:loading.remove wire:target="save">Save register</span><span wire:loading wire:target="save">Saving...</span></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</section>
