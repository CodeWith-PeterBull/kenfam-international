<div id="commerce-category-manager">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="category-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div><h3 id="category-table-title" class="card-title mb-1">Product categories</h3><p class="aureon-muted fs-12 mb-0">Hierarchical storefront and reporting organisation</p></div>
            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_PRODUCTS)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-folder-plus me-2"></i>Add category</button>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 aureon-category-table">
                <thead><tr><th scope="col">Category</th><th scope="col">Parent</th><th scope="col">Products</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td><div class="d-flex align-items-center gap-3">@if ($category->getFirstMediaUrl('category_image'))<img src="{{ $category->getFirstMediaUrl('category_image') }}" alt="" class="aureon-category-thumb">@else<span class="aureon-category-thumb aureon-product-thumb--placeholder"><i class="ti ti-folder"></i></span>@endif<div><span class="d-block fw-semibold">{{ $category->name }}</span><code class="aureon-event-name">{{ $category->slug }}</code></div></div></td>
                            <td>{{ $category->parent?->name ?? 'Top level' }}</td>
                            <td>{{ number_format($category->products_count) }}</td>
                            <td>{{ number_format($category->sort_order) }}</td>
                            <td><span class="badge {{ $category->is_active ? 'aureon-commerce-badge aureon-commerce-badge--published' : 'aureon-commerce-badge aureon-commerce-badge--neutral' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end text-nowrap">@can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_PRODUCTS)<button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $category->id }})" title="Edit category" aria-label="Edit {{ $category->name }}"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $category->id }})" title="{{ $category->is_active ? 'Hide' : 'Activate' }} category" aria-label="{{ $category->is_active ? 'Hide' : 'Activate' }} {{ $category->name }}"><i class="ti {{ $category->is_active ? 'ti-eye-off' : 'ti-eye' }}"></i></button>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5"><span class="aureon-empty-state__icon"><i class="ti ti-folders-off"></i></span><h4 class="fs-16 mb-1">No categories configured</h4><p class="aureon-muted mb-0">Create a category when the catalogue needs organisation.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedCategoryId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="category-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><div><h3 id="category-form-title" class="modal-title fs-18">{{ $editing ? 'Edit category' : 'Create category' }}</h3><p class="aureon-muted fs-12 mb-0">Navigation hierarchy, visibility, image, and search metadata</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close category form"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-md-6"><label for="category-name" class="form-label">Name <span class="text-danger">*</span></label><input id="category-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.live.blur="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="category-slug" class="form-label">URL slug</label><input id="category-slug" type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name when blank">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-8"><label for="category-parent" class="form-label">Parent category</label><select id="category-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"><option value="">Top level</option>@foreach ($this->parentOptions as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('form.parentId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="category-sort" class="form-label">Sort order</label><input id="category-sort" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder">@error('form.sortOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="category-description" class="form-label">Description</label><textarea id="category-description" rows="4" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><div class="form-check form-switch"><input id="category-active" type="checkbox" class="form-check-input" wire:model="form.isActive"><label for="category-active" class="form-check-label">Active in catalogue navigation</label></div></div>
                            <div class="col-12"><div class="aureon-category-image-editor">@if ($categoryImageUpload && $categoryImageUpload->isPreviewable() && ! $errors->has('categoryImageUpload'))<img src="{{ $categoryImageUpload->temporaryUrl() }}" alt="New category image preview">@elseif ($form->category?->getFirstMediaUrl('category_image'))<img src="{{ $form->category->getFirstMediaUrl('category_image') }}" alt="Current category image">@else<span><i class="ti ti-photo"></i></span>@endif</div><label for="category-image" class="form-label mt-3">Category image</label><input id="category-image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('categoryImageUpload') is-invalid @enderror" wire:model="categoryImageUpload">@error('categoryImageUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror<div wire:loading wire:target="categoryImageUpload" class="aureon-muted fs-12 mt-2">Preparing image...</div>@if ($form->category?->getFirstMedia('category_image'))<div class="form-check mt-3"><input id="remove-category-image" type="checkbox" class="form-check-input" wire:model="removeCategoryImage"><label for="remove-category-image" class="form-check-label">Remove current image</label></div>@endif</div>
                            <div class="col-12"><label for="category-meta-title" class="form-label">Meta title</label><input id="category-meta-title" type="text" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle">@error('form.metaTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="category-meta-description" class="form-label">Meta description</label><textarea id="category-meta-description" rows="3" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"></textarea>@error('form.metaDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,categoryImageUpload"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create category' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close category form"></button>
    @endif
</div>
