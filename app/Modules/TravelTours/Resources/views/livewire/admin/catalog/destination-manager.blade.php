<div id="travel-destination-manager">
    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-destination-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h3 id="travel-destination-title" class="card-title mb-1">Destination directory</h3><p class="aureon-muted mb-0">Geographic hierarchy and public editorial assets</p></div>
            @can('create', \App\Modules\TravelTours\Catalog\Models\Destination::class)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-map-pin-plus me-2" aria-hidden="true"></i>Add destination</button>
            @endcan
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6"><label for="travel-destination-search" class="form-label">Search destinations</label><div class="input-group"><span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span><input id="travel-destination-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Name, code, or country"></div></div>
                <div class="col-sm-5 col-lg-2"><label for="travel-destination-type-filter" class="form-label">Type</label><select id="travel-destination-type-filter" class="form-select" wire:model.live="typeFilter"><option value="">All types</option>@foreach(\App\Modules\TravelTours\Catalog\Enums\DestinationType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
                <div class="col-sm-5 col-lg-2"><label for="travel-destination-status-filter" class="form-label">Status</label><select id="travel-destination-status-filter" class="form-select" wire:model.live="statusFilter"><option value="">All states</option>@foreach(\App\Modules\TravelTours\Catalog\Enums\PublicationStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
                <div class="col-sm-2 col-lg-2"><button type="button" class="btn btn-outline-secondary w-100" wire:click="clearFilters"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead><tr><th scope="col">Destination</th><th scope="col">Hierarchy</th><th scope="col">Catalog use</th><th scope="col">State</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($destinations as $destination)
                        @php($cover = $destination->getFirstMedia('destination_cover'))
                        <tr wire:key="travel-destination-{{ $destination->id }}">
                            <td><div class="d-flex align-items-center gap-3">@if($cover)<img class="travel-admin-thumb" src="{{ $cover->hasGeneratedConversion('thumb') ? $cover->getUrl('thumb') : $cover->getUrl() }}" alt="{{ $cover->getCustomProperty('alt_text', '') }}">@else<span class="travel-admin-thumb travel-admin-thumb--empty"><i class="ti ti-photo" aria-hidden="true"></i></span>@endif<div class="min-w-0"><strong class="d-block text-break">{{ $destination->name }}</strong><small class="aureon-muted">{{ $destination->code ?: $destination->slug }}</small></div></div></td>
                            <td><span class="travel-status travel-status--muted">{{ $destination->type->label() }}</span><small class="d-block aureon-muted mt-1">{{ $destination->parent?->name ?? 'Top level' }}@if($destination->country_code) &middot; {{ $destination->country_code }}@endif</small></td>
                            <td>{{ number_format($destination->tours_count) }} tours<small class="d-block aureon-muted">{{ number_format($destination->children_count) }} child destinations</small></td>
                            <td><span class="travel-status {{ $destination->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Published ? 'travel-status--active' : ($destination->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Review ? 'travel-status--review' : 'travel-status--muted') }}">{{ $destination->status->label() }}</span><small class="d-block mt-1 {{ $destination->is_active ? 'text-success' : 'aureon-muted' }}">{{ $destination->is_active ? 'Active' : 'Hidden' }}</small></td>
                            <td class="text-end text-nowrap">
                                @can('update', $destination)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $destination->id }})" title="Edit destination" aria-label="Edit {{ $destination->name }}"><i class="ti ti-edit" aria-hidden="true"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMedia({{ $destination->id }})" title="Manage images" aria-label="Manage images for {{ $destination->name }}"><i class="ti ti-photo" aria-hidden="true"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleActive({{ $destination->id }})" title="{{ $destination->is_active ? 'Hide' : 'Activate' }} destination" aria-label="{{ $destination->is_active ? 'Hide' : 'Activate' }} {{ $destination->name }}"><i class="ti {{ $destination->is_active ? 'ti-eye-off' : 'ti-eye' }}" aria-hidden="true"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 aureon-muted">No destinations match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($destinations->hasPages())<div class="card-footer">{{ $destinations->links() }}</div>@endif
    </section>

    @if($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-destination-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><div><h3 id="travel-destination-form-title" class="modal-title fs-18">{{ $selectedDestinationId ? 'Edit destination' : 'Add destination' }}</h3><p class="aureon-muted mb-0 fs-12">Editorial metadata only; publication remains a separate governed action.</p></div><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="closeDialog" aria-label="Close destination form"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                            <div class="col-md-4"><label for="travel-destination-type" class="form-label">Type</label><select id="travel-destination-type" class="form-select @error('form.type') is-invalid @enderror" wire:model="form.type">@foreach(\App\Modules\TravelTours\Catalog\Enums\DestinationType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('form.type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-8"><label for="travel-destination-name" class="form-label">Name</label><input id="travel-destination-name" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" required>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-destination-slug" class="form-label">URL slug</label><input id="travel-destination-slug" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-destination-parent" class="form-label">Parent destination</label><select id="travel-destination-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"><option value="">Top level</option>@foreach($this->parentOptions as $option)<option value="{{ $option->id }}">{{ $option->name }} &middot; {{ $option->type->label() }}</option>@endforeach</select>@error('form.parentId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-3"><label for="travel-destination-country" class="form-label">Country code</label><input id="travel-destination-country" maxlength="2" class="form-control text-uppercase @error('form.countryCode') is-invalid @enderror" wire:model="form.countryCode" placeholder="KE">@error('form.countryCode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-3"><label for="travel-destination-code" class="form-label">Operational code</label><input id="travel-destination-code" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" placeholder="NBO">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="travel-destination-timezone" class="form-label">IANA timezone</label><input id="travel-destination-timezone" class="form-control @error('form.timezone') is-invalid @enderror" wire:model="form.timezone" placeholder="Africa/Nairobi">@error('form.timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-2"><label for="travel-destination-order" class="form-label">Sort order</label><input id="travel-destination-order" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder">@error('form.sortOrder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="travel-destination-summary" class="form-label">Short description</label><textarea id="travel-destination-summary" rows="2" class="form-control @error('form.shortDescription') is-invalid @enderror" wire:model="form.shortDescription"></textarea>@error('form.shortDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label for="travel-destination-description" class="form-label">Destination narrative</label><textarea id="travel-destination-description" rows="5" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="travel-destination-latitude" class="form-label">Latitude</label><input id="travel-destination-latitude" inputmode="decimal" class="form-control @error('form.latitude') is-invalid @enderror" wire:model="form.latitude">@error('form.latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="travel-destination-longitude" class="form-label">Longitude</label><input id="travel-destination-longitude" inputmode="decimal" class="form-control @error('form.longitude') is-invalid @enderror" wire:model="form.longitude">@error('form.longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label for="travel-destination-status" class="form-label">Editorial state</label>@if(in_array($form->status, ['published', 'archived'], true))<select id="travel-destination-status" class="form-select" disabled><option>{{ \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::from($form->status)->label() }}</option></select><small class="aureon-muted">Changed from the publication workflow.</small>@else<select id="travel-destination-status" class="form-select @error('form.status') is-invalid @enderror" wire:model="form.status"><option value="draft">Draft</option><option value="review">Review</option></select>@error('form.status')<div class="invalid-feedback">{{ $message }}</div>@enderror@endif</div>
                            <div class="col-md-6"><label for="travel-destination-meta-title" class="form-label">SEO title</label><input id="travel-destination-meta-title" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle">@error('form.metaTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="travel-destination-meta-description" class="form-label">SEO description</label><textarea id="travel-destination-meta-description" rows="2" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"></textarea>@error('form.metaDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12 d-flex flex-wrap gap-4"><div class="form-check form-switch"><input id="travel-destination-active" type="checkbox" class="form-check-input" wire:model="form.isActive"><label for="travel-destination-active" class="form-check-label">Active for assignment</label></div><div class="form-check form-switch"><input id="travel-destination-featured" type="checkbox" class="form-check-input" wire:model="form.isFeatured"><label for="travel-destination-featured" class="form-check-label">Featured placement</label></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save destination</button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if($dialog === 'media' && $this->selectedDestination)
        @php($selected = $this->selectedDestination)
        @php($cover = $selected->getFirstMedia('destination_cover'))
        @php($gallery = $selected->getMedia('destination_gallery'))
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-destination-media-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header"><div><h3 id="travel-destination-media-title" class="modal-title fs-18">{{ $selected->name }} images</h3><p class="aureon-muted mb-0 fs-12">Accessible cover and ordered gallery media</p></div><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="closeDialog" aria-label="Close image manager"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                    <div class="modal-body">
                        @error('media')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-4">
                            <section class="col-lg-5" aria-labelledby="travel-cover-title"><h4 id="travel-cover-title" class="fs-16">Cover</h4>@if($cover)<div class="travel-admin-media-item mb-3"><img src="{{ $cover->hasGeneratedConversion('card') ? $cover->getUrl('card') : $cover->getUrl() }}" alt="{{ $cover->getCustomProperty('alt_text', '') }}"><p>{{ $cover->getCustomProperty('alt_text', '') }}</p><div class="travel-admin-media-actions"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $cover->id }})">Edit text</button><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeCover" wire:confirm="Remove this destination cover?">Remove cover</button></div></div>@endif<form wire:submit="replaceCover" class="row g-2"><div class="col-12"><label for="travel-cover-upload" class="form-label">{{ $cover ? 'Replace cover' : 'Add cover' }}</label><input id="travel-cover-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('coverUpload') is-invalid @enderror" wire:model="coverUpload">@error('coverUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label for="travel-cover-alt" class="form-label">Alternative text</label><input id="travel-cover-alt" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText">@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label for="travel-cover-caption" class="form-label">Caption</label><input id="travel-cover-caption" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption">@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><button type="submit" class="btn btn-primary">Save cover</button></div></form></section>
                            <section class="col-lg-7" aria-labelledby="travel-gallery-title"><div class="d-flex justify-content-between gap-3"><div><h4 id="travel-gallery-title" class="fs-16 mb-1">Gallery</h4><p class="aureon-muted fs-12">{{ number_format($gallery->count()) }} / {{ config('travel-tours.media.destination_gallery_limit', 12) }} images</p></div></div><div class="travel-admin-media-grid mb-3">@forelse($gallery as $image)<article class="travel-admin-media-item" wire:key="travel-gallery-{{ $image->id }}"><img src="{{ $image->getUrl('thumb') }}" alt="{{ $image->getCustomProperty('alt_text', '') }}"><p>{{ $image->getCustomProperty('alt_text', '') }}</p><div class="travel-admin-media-actions"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $image->id }})" aria-label="Edit image text"><i class="ti ti-pencil" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'up')" aria-label="Move image earlier"><i class="ti ti-arrow-left" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'down')" aria-label="Move image later"><i class="ti ti-arrow-right" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeGalleryImage({{ $image->id }})" wire:confirm="Remove this gallery image?" aria-label="Remove image"><i class="ti ti-trash" aria-hidden="true"></i></button></div></article>@empty<p class="aureon-muted">No gallery images yet.</p>@endforelse</div><form wire:submit="addGalleryImage" class="row g-2"><div class="col-12"><label for="travel-gallery-upload" class="form-label">Add gallery image</label><input id="travel-gallery-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('galleryUpload') is-invalid @enderror" wire:model="galleryUpload">@error('galleryUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label for="travel-gallery-alt" class="form-label">Alternative text</label><input id="travel-gallery-alt" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText">@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label for="travel-gallery-caption" class="form-label">Caption</label><input id="travel-gallery-caption" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption">@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><button type="submit" class="btn btn-primary">Add image</button></div></form></section>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Done</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if($dialog === 'metadata' && $this->selectedDestination)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="travel-media-metadata-title" wire:keydown.escape.window="closeMetadata">
            <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="saveMediaMetadata">
                    <div class="modal-header"><h3 id="travel-media-metadata-title" class="modal-title fs-18">Edit image text</h3><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="closeMetadata" aria-label="Close image text form"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                    <div class="modal-body">@error('media')<div class="alert alert-danger">{{ $message }}</div>@enderror<div class="mb-3"><label for="travel-media-alt" class="form-label">Alternative text</label><input id="travel-media-alt" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText" required>@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div><label for="travel-media-caption" class="form-label">Caption</label><textarea id="travel-media-caption" rows="3" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption"></textarea>@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeMetadata">Cancel</button><button type="submit" class="btn btn-primary">Save text</button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
