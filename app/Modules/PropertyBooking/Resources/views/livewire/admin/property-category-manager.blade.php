<div id="property-booking-category-manager" class="pb-workspace">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="property-category-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="property-category-title" class="card-title mb-1">Property categories</h3>
                <p class="aureon-muted fs-12 mb-0">{{ number_format($this->categories->count()) }} taxonomy records</p>
            </div>
            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)
                <button type="button" class="btn btn-primary" wire:click="openCreate" wire:loading.attr="disabled">
                    <i class="ti ti-folder-plus me-2"></i>Add category
                </button>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pb-table pb-table--categories">
                <thead><tr><th scope="col">Category</th><th scope="col">Parent</th><th scope="col">Properties</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->categories as $category)
                        <tr wire:key="property-category-{{ $category->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($category->getFirstMediaUrl('category_image', 'thumb'))
                                        <img src="{{ $category->getFirstMediaUrl('category_image', 'thumb') }}" alt="{{ $category->getFirstMedia('category_image')?->getCustomProperty('alt_text', '') }}" class="pb-thumb pb-thumb--sm">
                                    @else
                                        <span class="pb-thumb pb-thumb--sm pb-thumb--placeholder" aria-hidden="true"><i class="ti ti-category"></i></span>
                                    @endif
                                    <div class="min-w-0"><strong class="d-block text-break">{{ $category->name }}</strong><code class="pb-code">{{ $category->slug }}</code></div>
                                </div>
                            </td>
                            <td>{{ $category->parent?->name ?? 'Top level' }}</td>
                            <td>{{ number_format($category->properties_count) }}</td>
                            <td>{{ number_format($category->sort_order) }}</td>
                            <td><span class="pb-badge {{ $category->is_active ? 'pb-badge--success' : 'pb-badge--neutral' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $category->id }})" data-bs-toggle="tooltip" title="Edit category" aria-label="Edit {{ $category->name }}"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $category->id }})" data-bs-toggle="tooltip" title="{{ $category->is_active ? 'Hide category' : 'Activate category' }}" aria-label="{{ $category->is_active ? 'Hide' : 'Activate' }} {{ $category->name }}"><i class="ti {{ $category->is_active ? 'ti-eye-off' : 'ti-eye' }}"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="pb-empty"><i class="ti ti-folders-off" aria-hidden="true"></i><strong>No property categories</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedCategoryId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="property-category-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header">
                        <div><h3 id="property-category-form-title" class="modal-title fs-18">{{ $editing ? 'Edit property category' : 'Create property category' }}</h3><p class="aureon-muted fs-12 mb-0">Classification and navigation image</p></div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close category form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-md-7"><label for="pb-category-name" class="form-label">Name <span class="text-danger">*</span></label><input id="pb-category-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.live.blur="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-5"><label for="pb-category-sort" class="form-label">Sort order</label><input id="pb-category-sort" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder">@error('form.sortOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="pb-category-slug" class="form-label">URL slug</label><input id="pb-category-slug" type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="pb-category-parent" class="form-label">Parent category</label><select id="pb-category-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"><option value="">Top level</option>@foreach ($this->parentOptions as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>@error('form.parentId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="pb-category-description" class="form-label">Description</label><textarea id="pb-category-description" rows="4" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-7"><label for="pb-category-image" class="form-label">Category image</label><input id="pb-category-image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('categoryImageUpload') is-invalid @enderror" wire:model="categoryImageUpload">@error('categoryImageUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-5"><label for="pb-category-alt" class="form-label">Image alternative text</label><input id="pb-category-alt" type="text" class="form-control @error('categoryImageAlt') is-invalid @enderror" wire:model="categoryImageAlt">@error('categoryImageAlt')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            @if ($categoryImageUpload)
                                <div class="col-12"><img src="{{ $categoryImageUpload->temporaryUrl() }}" alt="Image preview" class="pb-upload-preview"></div>
                            @elseif ($form->category?->getFirstMediaUrl('category_image', 'thumb'))
                                <div class="col-12 d-flex align-items-center gap-3"><img src="{{ $form->category->getFirstMediaUrl('category_image', 'thumb') }}" alt="{{ $categoryImageAlt }}" class="pb-upload-preview"><div class="form-check"><input id="pb-remove-category-image" type="checkbox" class="form-check-input" wire:model="removeCategoryImage"><label for="pb-remove-category-image" class="form-check-label">Remove current image</label></div></div>
                            @endif
                            <div class="col-12"><div class="form-check form-switch"><input id="pb-category-active" type="checkbox" class="form-check-input" wire:model="form.isActive"><label for="pb-category-active" class="form-check-label">Active</label></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create category' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
