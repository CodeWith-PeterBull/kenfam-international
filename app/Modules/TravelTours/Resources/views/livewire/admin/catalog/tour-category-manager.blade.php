<div id="travel-tour-category-manager">
    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-category-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h3 id="travel-category-title" class="card-title mb-1">Categories</h3><p class="aureon-muted mb-0">{{ number_format($this->categories->count()) }} taxonomy records</p></div>
            @can('create', \App\Modules\TravelTours\Catalog\Models\TourCategory::class)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-folder-plus me-2" aria-hidden="true"></i>Add category</button>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead><tr><th scope="col">Category</th><th scope="col">Parent</th><th scope="col">Tours</th><th scope="col">Order</th><th scope="col">Visibility</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($this->categories as $category)
                        <tr wire:key="travel-category-{{ $category->id }}">
                            <td><strong class="d-block text-break">{{ $category->name }}</strong><small class="aureon-muted">{{ $category->slug }}</small></td>
                            <td>{{ $category->parent?->name ?? 'Top level' }}</td>
                            <td>{{ number_format($category->tours_count) }}</td>
                            <td>{{ number_format($category->sort_order) }}</td>
                            <td><span class="travel-status {{ $category->is_active ? 'travel-status--active' : 'travel-status--muted' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can('update', $category)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $category->id }})" title="Edit category" aria-label="Edit {{ $category->name }}"><i class="ti ti-edit" aria-hidden="true"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $category->id }})" title="{{ $category->is_active ? 'Hide' : 'Activate' }} category" aria-label="{{ $category->is_active ? 'Hide' : 'Activate' }} {{ $category->name }}"><i class="ti {{ $category->is_active ? 'ti-eye-off' : 'ti-eye' }}" aria-hidden="true"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 aureon-muted">No tour categories have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-category-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><h3 id="travel-category-form-title" class="modal-title fs-18">{{ $selectedCategoryId ? 'Edit category' : 'Add category' }}</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close category form"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-md-8"><label for="travel-category-name" class="form-label">Name</label><input id="travel-category-name" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" required>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="travel-category-order" class="form-label">Sort order</label><input id="travel-category-order" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder">@error('form.sortOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-category-slug" class="form-label">URL slug</label><input id="travel-category-slug" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-category-parent" class="form-label">Parent category</label><select id="travel-category-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"><option value="">Top level</option>@foreach($this->parentOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>@error('form.parentId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="travel-category-description" class="form-label">Description</label><textarea id="travel-category-description" rows="3" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-category-icon" class="form-label">Icon key</label><input id="travel-category-icon" class="form-control @error('form.iconKey') is-invalid @enderror" wire:model="form.iconKey" placeholder="e.g. map-pin">@error('form.iconKey')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="travel-category-active" type="checkbox" class="form-check-input" wire:model="form.isActive"><label for="travel-category-active" class="form-check-label">Active</label></div></div>
                            <div class="col-md-6"><label for="travel-category-meta-title" class="form-label">SEO title</label><input id="travel-category-meta-title" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle">@error('form.metaTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-category-meta-description" class="form-label">SEO description</label><textarea id="travel-category-meta-description" rows="2" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"></textarea>@error('form.metaDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save category</button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
