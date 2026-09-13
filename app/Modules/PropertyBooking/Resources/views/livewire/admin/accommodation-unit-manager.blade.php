<div id="property-booking-accommodation-unit-manager" class="pb-workspace">
    @php($stats = $this->statistics)
    @include('property-booking::livewire.admin.partials.stat-grid', ['cards' => [
        ['label' => 'Concrete units', 'value' => $stats['total'], 'icon' => 'ti-door', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Ready', 'value' => $stats['ready'], 'icon' => 'ti-circle-check', 'color' => '#2f7d64'],
        ['label' => 'Needs attention', 'value' => $stats['attention'], 'icon' => 'ti-brush', 'color' => '#9b6a23'],
        ['label' => 'Inactive', 'value' => $stats['inactive'], 'icon' => 'ti-circle-off', 'color' => '#707782'],
    ]])

    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel" aria-labelledby="concrete-unit-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h3 id="concrete-unit-title" class="card-title mb-1">Concrete units</h3><p class="aureon-muted fs-12 mb-0">Room, apartment, house, and space inventory</p></div>
            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)<button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-door-enter me-2"></i>Add unit</button>@endcan
        </div>
        <div class="pb-filterbar pb-filterbar--wide">
            <div class="pb-filterbar__search"><label for="unit-search" class="visually-hidden">Search units</label><i class="ti ti-search"></i><input id="unit-search" type="search" class="form-control" placeholder="Code, name, floor" wire:model.live.debounce.300ms="search"></div>
            <div><label for="unit-property-filter" class="visually-hidden">Property</label><select id="unit-property-filter" class="form-select" wire:model.live="propertyFilter"><option value="">All properties</option>@foreach ($this->propertyOptions as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select></div>
            <div><label for="unit-type-filter" class="visually-hidden">Unit type</label><select id="unit-type-filter" class="form-select" wire:model.live="unitTypeFilter"><option value="">All unit types</option>@foreach ($this->unitTypeOptions as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
            <div><label for="unit-readiness-filter" class="visually-hidden">Readiness</label><select id="unit-readiness-filter" class="form-select" wire:model.live="readinessFilter"><option value="">All readiness</option>@foreach ($this->readinessStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
            <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2"></i>Clear</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pb-table pb-table--units">
                <thead><tr><th>Unit</th><th>Property</th><th>Unit type</th><th>Readiness</th><th>Activity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($this->units as $unit)
                        @php($statusClass = match($unit->operational_status) {
                            \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Ready => 'pb-badge--success',
                            \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Maintenance, \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::OutOfService => 'pb-badge--danger',
                            default => 'pb-badge--warning',
                        })
                        <tr wire:key="accommodation-unit-{{ $unit->id }}">
                            <td><strong class="d-block">{{ $unit->display_name ?: $unit->code }}</strong><code class="pb-code">{{ $unit->code }}</code>@if($unit->floor_label)<small class="d-block aureon-muted mt-1">{{ $unit->floor_label }}</small>@endif</td>
                            <td><span class="d-block">{{ $unit->property->name }}</span><small class="aureon-muted">{{ $unit->property->code }}</small></td>
                            <td>{{ $unit->unitType->name }}</td>
                            <td>
                                <span class="pb-badge {{ $statusClass }}">{{ $unit->operational_status->label() }}</span>
                                @can('updateReadiness', $unit)
                                    <select class="form-select form-select-sm mt-2 pb-inline-select" aria-label="Change readiness for {{ $unit->code }}" wire:change="changeReadiness({{ $unit->id }}, $event.target.value)">
                                        <option value="">Change</option>@foreach ($this->readinessStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                                    </select>
                                @endcan
                            </td>
                            <td><span class="d-block">{{ number_format($unit->assignments_count) }} assignments</span><small class="aureon-muted">{{ number_format($unit->availability_blocks_count) }} blocks</small></td>
                            <td><span class="pb-badge {{ $unit->is_active ? 'pb-badge--success' : 'pb-badge--neutral' }}">{{ $unit->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $unit->id }})" data-bs-toggle="tooltip" title="Edit unit" aria-label="Edit {{ $unit->code }}"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $unit->id }})" data-bs-toggle="tooltip" title="{{ $unit->is_active ? 'Deactivate unit' : 'Activate unit' }}" aria-label="{{ $unit->is_active ? 'Deactivate' : 'Activate' }} {{ $unit->code }}"><i class="ti {{ $unit->is_active ? 'ti-player-pause' : 'ti-player-play' }}"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="archive({{ $unit->id }})" wire:confirm="Archive this concrete unit?" data-bs-toggle="tooltip" title="Archive unit" aria-label="Archive {{ $unit->code }}"><i class="ti ti-archive"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="pb-empty"><i class="ti ti-door-off"></i><strong>No concrete units match this view</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->units->hasPages())<div class="card-footer">{{ $this->units->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedUnitId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="unit-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" wire:submit="save">
                <div class="modal-header"><div><h3 id="unit-form-title" class="modal-title fs-18">{{ $editing ? 'Edit accommodation unit' : 'Create accommodation unit' }}</h3><p class="aureon-muted fs-12 mb-0">Physical inventory identity and staff location</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close unit form"></button></div>
                <div class="modal-body">@error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror<div class="row g-3">
                    <div class="col-md-6"><label for="unit-form-property" class="form-label">Property <span class="text-danger">*</span></label><select id="unit-form-property" class="form-select @error('form.propertyId') is-invalid @enderror" wire:model.live="form.propertyId" @disabled($editing)><option value="">Select property</option>@foreach ($this->propertyOptions as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select>@error('form.propertyId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="unit-form-type" class="form-label">Unit type <span class="text-danger">*</span></label><select id="unit-form-type" class="form-select @error('form.unitTypeId') is-invalid @enderror" wire:model="form.unitTypeId"><option value="">Select unit type</option>@foreach ($this->formUnitTypeOptions as $type)<option value="{{ $type->id }}">{{ $type->name }} ({{ $type->code }})</option>@endforeach</select>@error('form.unitTypeId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="unit-form-code" class="form-label">Operational code <span class="text-danger">*</span></label><input id="unit-form-code" type="text" class="form-control @error('form.code') is-invalid @enderror" wire:model="form.code">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-7"><label for="unit-form-name" class="form-label">Display name</label><input id="unit-form-name" type="text" class="form-control @error('form.displayName') is-invalid @enderror" wire:model="form.displayName">@error('form.displayName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="unit-form-floor" class="form-label">Floor, wing, or block</label><input id="unit-form-floor" type="text" class="form-control @error('form.floorLabel') is-invalid @enderror" wire:model="form.floorLabel">@error('form.floorLabel')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-7"><label for="unit-form-location" class="form-label">Staff location note</label><input id="unit-form-location" type="text" class="form-control @error('form.locationNote') is-invalid @enderror" wire:model="form.locationNote">@error('form.locationNote')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label for="unit-form-note" class="form-label">Internal note</label><textarea id="unit-form-note" rows="4" class="form-control @error('form.internalNote') is-invalid @enderror" wire:model="form.internalNote"></textarea>@error('form.internalNote')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create unit' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
            </form></div>
        </div><div class="modal-backdrop fade show"></div>
    @endif
</div>
