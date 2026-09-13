<div id="property-booking-amenity-manager" class="pb-workspace">
    @php($stats = $this->statistics)
    @include('property-booking::livewire.admin.partials.stat-grid', ['cards' => [
        ['label' => 'Amenities', 'value' => $stats['total'], 'icon' => 'ti-sparkles', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Active', 'value' => $stats['active'], 'icon' => 'ti-circle-check', 'color' => '#2f7d64'],
        ['label' => 'Property level', 'value' => $stats['property'], 'icon' => 'ti-building-estate', 'color' => '#2c6e93'],
        ['label' => 'Unit level', 'value' => $stats['unit'], 'icon' => 'ti-bed', 'color' => '#9b6a23'],
    ]])

    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel" aria-labelledby="amenity-register-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h3 id="amenity-register-title" class="card-title mb-1">Amenity register</h3><p class="aureon-muted fs-12 mb-0">Property and unit-type feature vocabulary</p></div>
            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)<button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2"></i>Add amenity</button>@endcan
        </div>
        <div class="pb-filterbar">
            <div class="pb-filterbar__search"><label for="amenity-search" class="visually-hidden">Search amenities</label><i class="ti ti-search" aria-hidden="true"></i><input id="amenity-search" type="search" class="form-control" placeholder="Name, slug, or icon" wire:model.live.debounce.300ms="search"></div>
            <div><label for="amenity-scope" class="visually-hidden">Amenity scope</label><select id="amenity-scope" class="form-select" wire:model.live="scopeFilter"><option value="">All scopes</option>@foreach ($this->scopes as $scope)<option value="{{ $scope->value }}">{{ $scope->label() }}</option>@endforeach</select></div>
            <div><label for="amenity-per-page" class="visually-hidden">Rows per page</label><select id="amenity-per-page" class="form-select" wire:model.live="perPage"><option value="10">10 rows</option><option value="15">15 rows</option><option value="25">25 rows</option><option value="50">50 rows</option></select></div>
            <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2"></i>Clear</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pb-table">
                <thead><tr><th>Amenity</th><th>Scope</th><th>Assignments</th><th>Order</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->amenities as $amenity)
                        <tr wire:key="amenity-{{ $amenity->id }}">
                            <td><div class="d-flex align-items-center gap-3"><span class="pb-icon-tile"><i class="ti ti-{{ $amenity->icon_key ?: 'sparkles' }}"></i></span><div><strong class="d-block">{{ $amenity->name }}</strong><code class="pb-code">{{ $amenity->slug }}</code></div></div></td>
                            <td><span class="pb-badge pb-badge--info">{{ $amenity->scope->label() }}</span></td>
                            <td><span class="d-block">{{ number_format($amenity->properties_count) }} properties</span><small class="aureon-muted">{{ number_format($amenity->unit_types_count) }} unit types</small></td>
                            <td>{{ number_format($amenity->sort_order) }}</td>
                            <td><span class="pb-badge {{ $amenity->is_active ? 'pb-badge--success' : 'pb-badge--neutral' }}">{{ $amenity->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end text-nowrap">@can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)<button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $amenity->id }})" data-bs-toggle="tooltip" title="Edit amenity" aria-label="Edit {{ $amenity->name }}"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $amenity->id }})" data-bs-toggle="tooltip" title="{{ $amenity->is_active ? 'Hide amenity' : 'Activate amenity' }}" aria-label="{{ $amenity->is_active ? 'Hide' : 'Activate' }} {{ $amenity->name }}"><i class="ti {{ $amenity->is_active ? 'ti-eye-off' : 'ti-eye' }}"></i></button>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="pb-empty"><i class="ti ti-sparkles-off" aria-hidden="true"></i><strong>No amenities match this view</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->amenities->hasPages())<div class="card-footer">{{ $this->amenities->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedAmenityId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="amenity-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" wire:submit="save">
                <div class="modal-header"><div><h3 id="amenity-form-title" class="modal-title fs-18">{{ $editing ? 'Edit amenity' : 'Create amenity' }}</h3><p class="aureon-muted fs-12 mb-0">Reusable feature definition</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close amenity form"></button></div>
                <div class="modal-body">@error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror<div class="row g-3">
                    <div class="col-md-7"><label for="amenity-name" class="form-label">Name <span class="text-danger">*</span></label><input id="amenity-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="amenity-scope-input" class="form-label">Scope <span class="text-danger">*</span></label><select id="amenity-scope-input" class="form-select @error('form.scope') is-invalid @enderror" wire:model="form.scope">@foreach ($this->scopes as $scope)<option value="{{ $scope->value }}">{{ $scope->label() }}</option>@endforeach</select>@error('form.scope')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-7"><label for="amenity-slug" class="form-label">URL slug</label><input id="amenity-slug" type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="amenity-icon" class="form-label">Tabler icon key</label><div class="input-group"><span class="input-group-text"><i class="ti ti-{{ $form->iconKey ?: 'sparkles' }}"></i></span><input id="amenity-icon" type="text" class="form-control @error('form.iconKey') is-invalid @enderror" wire:model.live.debounce.300ms="form.iconKey" placeholder="wifi">@error('form.iconKey')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                    <div class="col-12"><label for="amenity-description" class="form-label">Description</label><textarea id="amenity-description" rows="4" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><label for="amenity-sort" class="form-label">Sort order</label><input id="amenity-sort" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder">@error('form.sortOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-7 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="amenity-active" type="checkbox" class="form-check-input" wire:model="form.isActive"><label for="amenity-active" class="form-check-label">Active</label></div></div>
                </div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create amenity' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
            </form></div>
        </div><div class="modal-backdrop fade show"></div>
    @endif
</div>
