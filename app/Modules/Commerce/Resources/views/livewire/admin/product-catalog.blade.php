<div id="commerce-product-catalog">
    @php($stats = [
        ['label' => 'Products', 'value' => $this->statistics['total'], 'icon' => 'ti-packages', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Published', 'value' => $this->statistics['published'], 'icon' => 'ti-world-check', 'color' => '#198754'],
        ['label' => 'Drafts', 'value' => $this->statistics['drafts'], 'icon' => 'ti-file-pencil', 'color' => '#6c757d'],
        ['label' => 'Featured', 'value' => $this->statistics['featured'], 'icon' => 'ti-star', 'color' => 'var(--aureon-accent)'],
    ])

    <section class="row" aria-label="Product statistics">
        @foreach ($stats as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                        <div><h3>{{ number_format($stat['value']) }}</h3><p>{{ $stat['label'] }}</p></div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel mb-4" aria-labelledby="product-filters-title">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-5 col-md-6">
                    <label id="product-filters-title" for="product-search" class="form-label">Search products</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input id="product-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Name, SKU, or barcode">
                    </div>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="product-status-filter" class="form-label">Status</label>
                    <select id="product-status-filter" class="form-select" wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        @foreach ($this->statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="product-category-filter" class="form-label">Category</label>
                    <select id="product-category-filter" class="form-select" wire:model.live="categoryFilter">
                        <option value="">All categories</option>
                        @foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-2 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-icon" wire:click="clearFilters" title="Clear filters" aria-label="Clear product filters"><i class="ti ti-filter-off"></i></button>
                    @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_PRODUCTS)
                        <button type="button" class="btn btn-primary flex-grow-1 text-nowrap" wire:click="openCreate"><i class="ti ti-plus me-2"></i>Add product</button>
                    @endcan
                </div>
            </div>
        </div>
    </section>

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="product-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div><h3 id="product-table-title" class="card-title mb-1">Product catalogue</h3><p class="aureon-muted fs-12 mb-0">{{ number_format($this->products->total()) }} matching products</p></div>
            <div class="d-flex align-items-center gap-2">
                <span class="aureon-activity-loading position-static" wire:loading.delay wire:target="search, statusFilter, categoryFilter, perPage, page" aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Updating</span></span>
                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" title="Export the filtered catalogue as PDF">
                    <span wire:loading.remove wire:target="exportPdf"><i class="ti ti-file-type-pdf me-1"></i>Export PDF</span>
                    <span wire:loading wire:target="exportPdf"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparing</span>
                </button>
                <select class="form-select form-select-sm aureon-per-page" wire:model.live="perPage" aria-label="Products per page">
                    @foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}">{{ $size }} / page</option>@endforeach
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 aureon-commerce-table">
                <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Pricing</th><th scope="col">Stock</th><th scope="col">Status</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->products as $product)
                        @php($isStorefrontVisible = $product->isVisibleInStorefront())
                        <tr wire:key="product-{{ $product->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($product->getFirstMediaUrl('product_gallery'))
                                        <img src="{{ $product->getFirstMediaUrl('product_gallery') }}" alt="" class="aureon-product-thumb">
                                    @else
                                        <span class="aureon-product-thumb aureon-product-thumb--placeholder"><i class="ti ti-photo"></i></span>
                                    @endif
                                    <div><span class="d-block fw-semibold">{{ $product->name }}</span><code class="aureon-event-name">{{ $product->sku }}</code>@if ($product->is_featured)<span class="d-block aureon-muted fs-11 mt-1"><i class="ti ti-star-filled text-warning me-1"></i>Featured</span>@endif</div>
                                </div>
                            </td>
                            <td>{{ $product->category?->name ?? 'Uncategorised' }}</td>
                            <td>
                                <span class="d-block fw-semibold">{{ $this->money($product->effective_price_minor) }}</span>
                                @if ($product->isOnSale())<small class="aureon-muted text-decoration-line-through">{{ $this->money($product->price_minor) }}</small>@endif
                            </td>
                            <td>
                                @if ($product->track_stock)
                                    <span class="d-block fw-semibold {{ ($product->stock?->isLow() ?? true) ? 'text-warning' : '' }}">{{ number_format($product->stock?->on_hand ?? 0) }} {{ \Illuminate\Support\Str::plural($product->unit_label, $product->stock?->on_hand ?? 0) }}</span>
                                    <small class="aureon-muted">Low at {{ number_format($product->stock?->low_stock_threshold ?? 0) }}</small>
                                @else
                                    <span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">Not tracked</span>
                                @endif
                            </td>
                            <td><span class="badge aureon-commerce-badge aureon-commerce-badge--{{ $product->status->value }}">{{ $product->status->label() }}</span></td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if ($isStorefrontVisible)
                                        <a href="{{ route('commerce.storefront.products.show', ['product' => $product->slug]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-icon btn-sm btn-outline-secondary" data-storefront-product-link="available" title="View product in storefront" aria-label="View {{ $product->name }} in storefront in a new tab"><i class="ti ti-external-link" aria-hidden="true"></i></a>
                                    @else
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" data-storefront-product-link="unavailable" title="Product is not currently visible in the storefront" aria-label="{{ $product->name }} is not currently visible in the storefront" disabled><i class="ti ti-eye-off" aria-hidden="true"></i></button>
                                    @endif
                                    @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_PRODUCTS)
                                        <label for="product-status-{{ $product->id }}" class="visually-hidden">Change {{ $product->name }} status</label>
                                        <select id="product-status-{{ $product->id }}" class="form-select form-select-sm aureon-per-page" wire:change="changeStatus({{ $product->id }}, $event.target.value)">
                                            @foreach ($this->statuses as $status)<option value="{{ $status->value }}" @selected($product->status === $status)>{{ $status->label() }}</option>@endforeach
                                        </select>
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $product->id }})" title="Edit product" aria-label="Edit {{ $product->name }}"><i class="ti ti-edit"></i></button>
                                        <a href="{{ route('commerce.admin.catalog.barcodes', ['product' => $product->ulid]) }}" class="btn btn-icon btn-sm btn-outline-secondary" title="Print barcode label" aria-label="Print label for {{ $product->name }}"><i class="ti ti-barcode"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5"><span class="aureon-empty-state__icon"><i class="ti ti-package-off"></i></span><h4 class="fs-16 mb-1">No products found</h4><p class="aureon-muted mb-0">Adjust the filters or add the first product.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->products->hasPages())<div class="card-footer">{{ $this->products->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedProductId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="product-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><div><h3 id="product-form-title" class="modal-title fs-18">{{ $editing ? 'Edit product' : 'Create product' }}</h3><p class="aureon-muted fs-12 mb-0">Core catalogue, pricing, stock policy, media, and search metadata</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close product form"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <section class="aureon-form-section">
                            <h4>Product identity</h4>
                            <div class="row g-3">
                                <div class="col-lg-6"><label for="product-name" class="form-label">Name <span class="text-danger">*</span></label><input id="product-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.live.blur="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-lg-6"><label for="product-slug" class="form-label">URL slug</label><input id="product-slug" type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name when blank">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-3"><label for="product-sku" class="form-label">SKU <span class="text-danger">*</span></label><input id="product-sku" type="text" class="form-control @error('form.sku') is-invalid @enderror" wire:model="form.sku">@error('form.sku')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-3"><label for="product-barcode" class="form-label">Internal barcode</label><div class="input-group"><input id="product-barcode" type="text" class="form-control @error('form.barcode') is-invalid @enderror" wire:model="form.barcode" placeholder="Auto on save"><button class="btn btn-outline-secondary" type="button" wire:click="generateInternalBarcode" wire:loading.attr="disabled" title="Generate internal barcode"><i class="ti ti-refresh" aria-hidden="true"></i></button></div>@error('form.barcode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                                <div class="col-md-3"><label for="product-manufacturer-barcode" class="form-label">Manufacturer barcode</label><input id="product-manufacturer-barcode" type="text" class="form-control @error('form.manufacturerBarcode') is-invalid @enderror" wire:model="form.manufacturerBarcode" placeholder="Optional EAN/UPC">@error('form.manufacturerBarcode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-3"><label for="product-category" class="form-label">Category</label><select id="product-category" class="form-select @error('form.categoryId') is-invalid @enderror" wire:model="form.categoryId"><option value="">Uncategorised</option>@foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('form.categoryId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="product-short-description" class="form-label">Short description</label><textarea id="product-short-description" rows="2" class="form-control @error('form.shortDescription') is-invalid @enderror" wire:model="form.shortDescription"></textarea>@error('form.shortDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="product-description" class="form-label">Full description</label><textarea id="product-description" rows="5" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>
                        </section>

                        <section class="aureon-form-section">
                            <h4>Pricing and order rules</h4>
                            <div class="row g-3">
                                <div class="col-md-4"><label for="product-price" class="form-label">Regular price ({{ config('commerce.currency.code') }}) <span class="text-danger">*</span></label><input id="product-price" type="number" min="0" step="0.01" class="form-control @error('form.price') is-invalid @enderror" wire:model="form.price">@error('form.price')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-sale-price" class="form-label">Sale price</label><input id="product-sale-price" type="number" min="0" step="0.01" class="form-control @error('form.salePrice') is-invalid @enderror" wire:model="form.salePrice">@error('form.salePrice')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-cost-price" class="form-label">Internal cost</label><input id="product-cost-price" type="number" min="0" step="0.01" class="form-control @error('form.costPrice') is-invalid @enderror" wire:model="form.costPrice">@error('form.costPrice')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-sale-start" class="form-label">Sale starts</label><input id="product-sale-start" type="datetime-local" class="form-control @error('form.saleStartsAt') is-invalid @enderror" wire:model="form.saleStartsAt">@error('form.saleStartsAt')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-sale-end" class="form-label">Sale ends</label><input id="product-sale-end" type="datetime-local" class="form-control @error('form.saleEndsAt') is-invalid @enderror" wire:model="form.saleEndsAt">@error('form.saleEndsAt')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-tax-rate" class="form-label">Tax rate (%)</label><input id="product-tax-rate" type="number" min="0" max="100" step="0.01" class="form-control @error('form.taxRatePercent') is-invalid @enderror" wire:model="form.taxRatePercent">@error('form.taxRatePercent')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-unit" class="form-label">Unit label <span class="text-danger">*</span></label><input id="product-unit" type="text" class="form-control @error('form.unitLabel') is-invalid @enderror" wire:model="form.unitLabel">@error('form.unitLabel')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-min-order" class="form-label">Minimum order quantity</label><input id="product-min-order" type="number" min="1" class="form-control @error('form.minimumOrderQuantity') is-invalid @enderror" wire:model="form.minimumOrderQuantity">@error('form.minimumOrderQuantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="product-max-order" class="form-label">Maximum order quantity</label><input id="product-max-order" type="number" min="1" class="form-control @error('form.maximumOrderQuantity') is-invalid @enderror" wire:model="form.maximumOrderQuantity">@error('form.maximumOrderQuantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12 d-flex flex-wrap gap-4"><div class="form-check form-switch"><input id="product-tax-inclusive" class="form-check-input" type="checkbox" wire:model="form.isTaxInclusive"><label class="form-check-label" for="product-tax-inclusive">Prices include tax</label></div><div class="form-check form-switch"><input id="product-track-stock" class="form-check-input" type="checkbox" wire:model="form.trackStock"><label class="form-check-label" for="product-track-stock">Track stock</label></div><div class="form-check form-switch"><input id="product-featured" class="form-check-input" type="checkbox" wire:model="form.isFeatured"><label class="form-check-label" for="product-featured">Featured product</label></div></div>
                            </div>
                        </section>

                        <section class="aureon-form-section">
                            <h4>Physical details and specifications</h4>
                            <div class="row g-3">
                                <div class="col-md"><label for="product-weight" class="form-label">Weight (g)</label><input id="product-weight" type="number" min="0" class="form-control @error('form.weightGrams') is-invalid @enderror" wire:model="form.weightGrams">@error('form.weightGrams')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md"><label for="product-length" class="form-label">Length (mm)</label><input id="product-length" type="number" min="0" class="form-control @error('form.lengthMm') is-invalid @enderror" wire:model="form.lengthMm">@error('form.lengthMm')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md"><label for="product-width" class="form-label">Width (mm)</label><input id="product-width" type="number" min="0" class="form-control @error('form.widthMm') is-invalid @enderror" wire:model="form.widthMm">@error('form.widthMm')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md"><label for="product-height" class="form-label">Height (mm)</label><input id="product-height" type="number" min="0" class="form-control @error('form.heightMm') is-invalid @enderror" wire:model="form.heightMm">@error('form.heightMm')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="product-specifications" class="form-label">Specifications (JSON)</label><textarea id="product-specifications" rows="5" class="form-control font-monospace @error('form.specificationsJson') is-invalid @enderror" wire:model="form.specificationsJson" placeholder='{"Processor": "Example"}'></textarea>@error('form.specificationsJson')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>
                        </section>

                        <section class="aureon-form-section">
                            <h4>Product gallery</h4>
                            @if ($editing && $form->product?->getMedia('product_gallery')->isNotEmpty())
                                <div class="aureon-product-gallery mb-3">
                                    @foreach ($form->product->getMedia('product_gallery') as $media)
                                        <figure wire:key="product-media-{{ $media->id }}"><img src="{{ $media->getUrl() }}" alt=""><button type="button" class="btn btn-danger btn-icon btn-sm" wire:click="removeImage({{ $form->product->id }}, {{ $media->id }})" aria-label="Remove image"><i class="ti ti-trash"></i></button></figure>
                                    @endforeach
                                </div>
                            @endif
                            <label for="product-gallery" class="form-label">Add images</label><input id="product-gallery" type="file" multiple accept="image/jpeg,image/png,image/webp" class="form-control @error('galleryUploads') is-invalid @enderror @error('galleryUploads.*') is-invalid @enderror" wire:model="galleryUploads">@error('galleryUploads')<div class="invalid-feedback">{{ $message }}</div>@enderror @error('galleryUploads.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <p class="aureon-muted fs-12 mt-2 mb-0">Up to {{ config('commerce.media.product_gallery_limit', 8) }} JPEG, PNG, or WebP images per product.</p>
                            <div wire:loading wire:target="galleryUploads" class="aureon-muted fs-12 mt-2"><span class="spinner-border spinner-border-sm me-1"></span>Preparing images...</div>
                        </section>

                        <section class="aureon-form-section">
                            <h4>Search metadata</h4>
                            <div class="row g-3"><div class="col-12"><label for="product-meta-title" class="form-label">Meta title</label><input id="product-meta-title" type="text" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle">@error('form.metaTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label for="product-meta-description" class="form-label">Meta description</label><textarea id="product-meta-description" rows="3" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"></textarea>@error('form.metaDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                        </section>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,galleryUploads"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create product' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close product form"></button>
    @endif
</div>
