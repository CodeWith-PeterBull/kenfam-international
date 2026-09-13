<div id="property-booking-property-manager" class="pb-workspace">
    @php($stats = $this->statistics)
    @include('property-booking::livewire.admin.partials.stat-grid', ['cards' => [
        ['label' => 'Properties', 'value' => $stats['total'], 'icon' => 'ti-building-estate', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Published', 'value' => $stats['published'], 'icon' => 'ti-world-check', 'color' => '#2f7d64'],
        ['label' => 'Drafts', 'value' => $stats['draft'], 'icon' => 'ti-file-pencil', 'color' => '#2c6e93'],
        ['label' => 'Featured', 'value' => $stats['featured'], 'icon' => 'ti-star', 'color' => '#9b6a23'],
    ]])

    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel mb-4" aria-labelledby="property-directory-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h3 id="property-directory-title" class="card-title mb-1">Property directory</h3><p class="aureon-muted fs-12 mb-0">Authorized portfolio and listing readiness</p></div>
            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_PROPERTIES)<button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-building-plus me-2"></i>Add property</button>@endcan
        </div>
        <div class="pb-filterbar">
            <div class="pb-filterbar__search"><label for="property-search" class="visually-hidden">Search properties</label><i class="ti ti-search"></i><input id="property-search" type="search" class="form-control" placeholder="Name, code, city, or region" wire:model.live.debounce.300ms="search"></div>
            <div><label for="property-status-filter" class="visually-hidden">Property status</label><select id="property-status-filter" class="form-select" wire:model.live="statusFilter"><option value="">All statuses</option>@foreach ($this->statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
            <div><label for="property-category-filter" class="visually-hidden">Property category</label><select id="property-category-filter" class="form-select" wire:model.live="categoryFilter"><option value="">All categories</option>@foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="property-per-page" class="visually-hidden">Properties per page</label><select id="property-per-page" class="form-select" wire:model.live="perPage"><option value="6">6 cards</option><option value="12">12 cards</option><option value="24">24 cards</option><option value="48">48 cards</option></select></div>
            <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2"></i>Clear</button>
        </div>
    </section>

    <section class="pb-property-grid mb-4" aria-live="polite" wire:loading.class="pb-is-loading" wire:target="search,statusFilter,categoryFilter,perPage">
        @forelse ($this->properties as $property)
            @php($statusClass = match($property->status) {
                \App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus::Published => 'pb-badge--success',
                \App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus::Archived => 'pb-badge--neutral',
                default => 'pb-badge--info',
            })
            <article class="card aureon-panel pb-property-card" wire:key="property-{{ $property->id }}">
                <div class="pb-property-card__media">
                    @if ($property->getFirstMediaUrl('property_cover', 'card'))
                        <img src="{{ $property->getFirstMediaUrl('property_cover', 'card') }}" alt="{{ $property->getFirstMedia('property_cover')?->getCustomProperty('alt_text', '') }}">
                    @else
                        <span class="pb-property-card__placeholder" aria-hidden="true"><i class="ti ti-building-estate"></i></span>
                    @endif
                    <span class="pb-badge {{ $statusClass }} pb-property-card__status">{{ $property->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-2"><div class="min-w-0"><h4 class="pb-property-card__title">{{ $property->name }}</h4><code class="pb-code">{{ $property->code }}</code></div>@if($property->is_featured)<i class="ti ti-star-filled pb-featured" aria-label="Featured property"></i>@endif</div>
                    <p class="pb-property-card__location"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ collect([$property->city, $property->region, $property->country_code])->filter()->join(', ') ?: 'Location pending' }}</p>
                    <p class="pb-property-card__summary">{{ $property->short_description ?: 'Property summary pending.' }}</p>
                    <dl class="pb-count-grid"><div><dt>Types</dt><dd>{{ number_format($property->unit_types_count) }}</dd></div><div><dt>Units</dt><dd>{{ number_format($property->units_count) }}</dd></div><div><dt>Rates</dt><dd>{{ number_format($property->rate_plans_count) }}</dd></div></dl>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between gap-2">
                    <small class="aureon-muted">{{ $property->category?->name ?? 'Uncategorised' }}</small>
                    <div class="d-flex gap-1">
                        @can('update', $property)
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMedia({{ $property->id }})" data-bs-toggle="tooltip" title="Manage images" aria-label="Manage images for {{ $property->name }}"><i class="ti ti-photo"></i></button>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $property->id }})" data-bs-toggle="tooltip" title="Edit property" aria-label="Edit {{ $property->name }}"><i class="ti ti-edit"></i></button>
                            <select class="form-select form-select-sm pb-inline-select" aria-label="Change status for {{ $property->name }}" wire:change="changeStatus({{ $property->id }}, $event.target.value)"><option value="">Status</option>@foreach ($this->statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <div class="card aureon-panel pb-empty pb-empty--grid"><i class="ti ti-building-off"></i><strong>No properties match this view</strong></div>
        @endforelse
    </section>
    @if ($this->properties->hasPages())<div class="mb-4">{{ $this->properties->links() }}</div>@endif

    @if ($dialog === 'form')
        @php($editing = $selectedPropertyId !== null)
        @php($formProperty = $this->formProperty)
        @php($formPropertyCover = $formProperty?->getFirstMedia('property_cover'))
        @php($formPropertyGallery = $formProperty?->getMedia('property_gallery') ?? collect())
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="property-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" wire:submit="save">
                <div class="modal-header"><div><h3 id="property-form-title" class="modal-title fs-18">{{ $editing ? 'Edit property' : 'Create property' }}</h3><p class="aureon-muted fs-12 mb-0">Identity, contact, policy, discovery, and staff scope</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close property form"></button></div>
                <div class="modal-body">
                    @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <fieldset class="pb-form-section"><legend>Identity and listing</legend><div class="row g-3">
                        <div class="col-md-4"><label for="property-form-name" class="form-label">Name <span class="text-danger">*</span></label><input id="property-form-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-form-code" class="form-label">Operational code <span class="text-danger">*</span></label><input id="property-form-code" type="text" class="form-control @error('form.code') is-invalid @enderror" wire:model="form.code">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-form-category" class="form-label">Category</label><select id="property-form-category" class="form-select @error('form.categoryId') is-invalid @enderror" wire:model="form.categoryId"><option value="">Uncategorised</option>@foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('form.categoryId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-form-slug" class="form-label">URL slug</label><input id="property-form-slug" type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-currency" class="form-label">Currency <span class="text-danger">*</span></label><input id="property-form-currency" type="text" maxlength="3" class="form-control text-uppercase @error('form.currency') is-invalid @enderror" wire:model="form.currency">@error('form.currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-timezone" class="form-label">IANA timezone <span class="text-danger">*</span></label><input id="property-form-timezone" type="text" class="form-control @error('form.timezone') is-invalid @enderror" wire:model="form.timezone">@error('form.timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label for="property-form-short" class="form-label">Short description</label><textarea id="property-form-short" rows="2" class="form-control @error('form.shortDescription') is-invalid @enderror" wire:model="form.shortDescription"></textarea>@error('form.shortDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label for="property-form-description" class="form-label">Full description</label><textarea id="property-form-description" rows="5" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"></textarea>@error('form.description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><div class="form-check form-switch"><input id="property-form-featured" type="checkbox" class="form-check-input" wire:model="form.isFeatured"><label for="property-form-featured" class="form-check-label">Featured listing</label></div></div>
                    </div></fieldset>

                    <fieldset class="pb-form-section pb-form-media-editor"><legend>Listing media</legend>
                        <div class="pb-form-media-canvas mb-3">
                            @if($formCoverUpload)
                                <img src="{{ $formCoverUpload->temporaryUrl() }}" alt="New property cover preview">
                                <span class="pb-form-media-canvas__label">New cover</span>
                            @elseif($formPropertyCover)
                                <img src="{{ $formPropertyCover->getUrl('detail') }}" alt="{{ $formPropertyCover->getCustomProperty('alt_text', '') }}">
                                <span class="pb-form-media-canvas__label">Current cover</span>
                            @else
                                <div class="pb-form-media-placeholder"><i class="ti ti-photo-plus" aria-hidden="true"></i><strong>Property cover</strong><small>Landscape photography works best</small></div>
                            @endif
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-lg-4"><label for="property-form-cover" class="form-label">Cover image</label><input id="property-form-cover" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('formCoverUpload') is-invalid @enderror" wire:model="formCoverUpload">@error('formCoverUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-lg-4"><label for="property-form-cover-alt" class="form-label">Cover alternative text</label><input id="property-form-cover-alt" type="text" class="form-control @error('formCoverAltText') is-invalid @enderror" wire:model="formCoverAltText" placeholder="Describe what the image shows">@error('formCoverAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-lg-4"><label for="property-form-cover-caption" class="form-label">Cover caption</label><input id="property-form-cover-caption" type="text" class="form-control @error('formCoverCaption') is-invalid @enderror" wire:model="formCoverCaption" placeholder="Optional guest-facing caption">@error('formCoverCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div>
                        <div class="pb-section-heading"><div><h4>Gallery additions</h4><small>Upload several images now; ordering and existing-image maintenance remain available from Manage images.</small></div><span class="pb-badge pb-badge--neutral">{{ number_format($formPropertyGallery->count()) }} current</span></div>
                        <div class="mb-3"><label for="property-form-gallery" class="form-label">Gallery images</label><input id="property-form-gallery" type="file" multiple accept="image/jpeg,image/png,image/webp" class="form-control @error('formGalleryUploads') is-invalid @enderror @error('formGalleryUploads.*') is-invalid @enderror" wire:model="formGalleryUploads">@error('formGalleryUploads')<div class="invalid-feedback">{{ $message }}</div>@enderror @error('formGalleryUploads.*')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @if($formGalleryUploads !== [])
                            <div class="pb-form-gallery-editor mb-3">
                                @foreach($formGalleryUploads as $index => $upload)
                                    <article class="pb-form-gallery-editor__item" wire:key="property-form-gallery-{{ $index }}">
                                        <img src="{{ $upload->temporaryUrl() }}" alt="New gallery image {{ $index + 1 }} preview">
                                        <div class="pb-form-gallery-editor__fields">
                                            <label for="property-form-gallery-alt-{{ $index }}" class="form-label">Alternative text <span class="text-danger">*</span></label>
                                            <input id="property-form-gallery-alt-{{ $index }}" type="text" class="form-control @error('formGalleryAltTexts.'.$index) is-invalid @enderror" wire:model="formGalleryAltTexts.{{ $index }}">
                                            @error('formGalleryAltTexts.'.$index)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <label for="property-form-gallery-caption-{{ $index }}" class="form-label mt-2">Caption</label>
                                            <input id="property-form-gallery-caption-{{ $index }}" type="text" class="form-control @error('formGalleryCaptions.'.$index) is-invalid @enderror" wire:model="formGalleryCaptions.{{ $index }}">
                                            @error('formGalleryCaptions.'.$index)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @elseif($formPropertyGallery->isNotEmpty())
                            <div class="pb-form-gallery-existing mb-3" aria-label="Current property gallery">
                                @foreach($formPropertyGallery as $image)<img src="{{ $image->getUrl('thumb') }}" alt="{{ $image->getCustomProperty('alt_text', '') }}">@endforeach
                            </div>
                        @endif
                    </fieldset>

                    <fieldset class="pb-form-section"><legend>Contact and location</legend><div class="row g-3">
                        <div class="col-md-4"><label for="property-form-email" class="form-label">Email</label><input id="property-form-email" type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email">@error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-form-phone" class="form-label">Phone</label><input id="property-form-phone" type="tel" class="form-control @error('form.phone') is-invalid @enderror" wire:model="form.phone">@error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-form-whatsapp" class="form-label">WhatsApp phone</label><input id="property-form-whatsapp" type="tel" class="form-control @error('form.whatsappPhone') is-invalid @enderror" wire:model="form.whatsappPhone">@error('form.whatsappPhone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label for="property-form-website" class="form-label">Website URL</label><input id="property-form-website" type="url" class="form-control @error('form.websiteUrl') is-invalid @enderror" wire:model="form.websiteUrl">@error('form.websiteUrl')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-form-address1" class="form-label">Address line 1</label><input id="property-form-address1" type="text" class="form-control @error('form.addressLine1') is-invalid @enderror" wire:model="form.addressLine1">@error('form.addressLine1')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-form-address2" class="form-label">Address line 2</label><input id="property-form-address2" type="text" class="form-control @error('form.addressLine2') is-invalid @enderror" wire:model="form.addressLine2">@error('form.addressLine2')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-city" class="form-label">City</label><input id="property-form-city" type="text" class="form-control @error('form.city') is-invalid @enderror" wire:model="form.city">@error('form.city')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-region" class="form-label">Region</label><input id="property-form-region" type="text" class="form-control @error('form.region') is-invalid @enderror" wire:model="form.region">@error('form.region')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-postal" class="form-label">Postal code</label><input id="property-form-postal" type="text" class="form-control @error('form.postalCode') is-invalid @enderror" wire:model="form.postalCode">@error('form.postalCode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label for="property-form-country" class="form-label">Country code <span class="text-danger">*</span></label><input id="property-form-country" type="text" maxlength="2" class="form-control text-uppercase @error('form.countryCode') is-invalid @enderror" wire:model="form.countryCode">@error('form.countryCode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-form-lat" class="form-label">Latitude</label><input id="property-form-lat" type="text" inputmode="decimal" class="form-control @error('form.latitude') is-invalid @enderror" wire:model="form.latitude">@error('form.latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-form-lng" class="form-label">Longitude</label><input id="property-form-lng" type="text" inputmode="decimal" class="form-control @error('form.longitude') is-invalid @enderror" wire:model="form.longitude">@error('form.longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div></fieldset>

                    <fieldset class="pb-form-section"><legend>Operating policy</legend><div class="row g-3">
                        <div class="col-sm-6 col-lg-3"><label for="property-checkin-from" class="form-label">Check-in from</label><input id="property-checkin-from" type="time" class="form-control @error('form.checkInFrom') is-invalid @enderror" wire:model="form.checkInFrom">@error('form.checkInFrom')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-6 col-lg-3"><label for="property-checkin-until" class="form-label">Check-in until</label><input id="property-checkin-until" type="time" class="form-control @error('form.checkInUntil') is-invalid @enderror" wire:model="form.checkInUntil">@error('form.checkInUntil')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-6 col-lg-3"><label for="property-checkout-from" class="form-label">Check-out from</label><input id="property-checkout-from" type="time" class="form-control @error('form.checkOutFrom') is-invalid @enderror" wire:model="form.checkOutFrom">@error('form.checkOutFrom')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-6 col-lg-3"><label for="property-checkout-until" class="form-label">Check-out until</label><input id="property-checkout-until" type="time" class="form-control @error('form.checkOutUntil') is-invalid @enderror" wire:model="form.checkOutUntil">@error('form.checkOutUntil')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-notice" class="form-label">Minimum notice (minutes)</label><input id="property-notice" type="number" min="0" class="form-control @error('form.minimumNoticeMinutes') is-invalid @enderror" wire:model="form.minimumNoticeMinutes">@error('form.minimumNoticeMinutes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-advance" class="form-label">Maximum advance (days)</label><input id="property-advance" type="number" min="1" class="form-control @error('form.maximumAdvanceDays') is-invalid @enderror" wire:model="form.maximumAdvanceDays">@error('form.maximumAdvanceDays')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="property-turnover" class="form-label">Turnover buffer (minutes)</label><input id="property-turnover" type="number" min="0" class="form-control @error('form.turnoverMinutes') is-invalid @enderror" wire:model="form.turnoverMinutes">@error('form.turnoverMinutes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-rules" class="form-label">House rules</label><textarea id="property-rules" rows="4" class="form-control @error('form.houseRules') is-invalid @enderror" wire:model="form.houseRules"></textarea>@error('form.houseRules')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-cancellation" class="form-label">Cancellation summary</label><textarea id="property-cancellation" rows="4" class="form-control @error('form.cancellationSummary') is-invalid @enderror" wire:model="form.cancellationSummary"></textarea>@error('form.cancellationSummary')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div></fieldset>

                    <fieldset class="pb-form-section"><legend>Assignments and discovery</legend><div class="row g-3">
                        <div class="col-lg-4"><label for="property-amenities" class="form-label">Property amenities</label><select id="property-amenities" class="form-select @error('form.amenityIds') is-invalid @enderror" multiple size="7" wire:model="form.amenityIds">@foreach ($this->amenityOptions as $amenity)<option value="{{ $amenity->id }}">{{ $amenity->name }}</option>@endforeach</select>@error('form.amenityIds')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-lg-4"><label for="property-users" class="form-label">Assigned users</label><select id="property-users" class="form-select @error('form.assignedUserIds') is-invalid @enderror" multiple size="7" wire:model.live="form.assignedUserIds">@foreach ($this->userOptions as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>@endforeach</select>@error('form.assignedUserIds')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-lg-4"><label for="property-default-user" class="form-label">Default assigned user</label><select id="property-default-user" class="form-select @error('form.defaultUserId') is-invalid @enderror" wire:model="form.defaultUserId"><option value="">None</option>@foreach ($this->userOptions->whereIn('id', collect($form->assignedUserIds)->map(fn($id) => (int) $id)) as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>@error('form.defaultUserId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-meta-title" class="form-label">Meta title</label><input id="property-meta-title" type="text" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle">@error('form.metaTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="property-meta-description" class="form-label">Meta description</label><textarea id="property-meta-description" rows="2" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"></textarea>@error('form.metaDescription')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div></fieldset>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create property' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
            </form></div>
        </div><div class="modal-backdrop fade show"></div>
    @elseif ($dialog === 'media' && $this->mediaProperty)
        @php($mediaProperty = $this->mediaProperty)
        @php($cover = $mediaProperty->getFirstMedia('property_cover'))
        @php($gallery = $mediaProperty->getMedia('property_gallery'))
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="property-media-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><h3 id="property-media-title" class="modal-title fs-18">{{ $mediaProperty->name }} media</h3><p class="aureon-muted fs-12 mb-0">Cover and ordered gallery</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close media workspace"></button></div>
                <div class="modal-body">
                    @error('media')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <section class="pb-media-section" aria-labelledby="property-cover-title"><div class="pb-section-heading"><div><h4 id="property-cover-title">Cover image</h4><small>{{ $cover ? 'Configured' : 'Not configured' }}</small></div>@if($cover)<div class="d-flex gap-1"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $cover->id }})" title="Edit metadata" aria-label="Edit cover metadata"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeCover" wire:confirm="Remove this cover image?" title="Remove cover" aria-label="Remove cover"><i class="ti ti-trash"></i></button></div>@endif</div>
                        <div class="row g-3 align-items-start">@if($cover)<div class="col-lg-4"><img src="{{ $cover->getUrl('card') }}" alt="{{ $cover->getCustomProperty('alt_text', '') }}" class="pb-media-cover"></div>@endif<div class="{{ $cover ? 'col-lg-8' : 'col-12' }}"><form wire:submit="replaceCover" class="row g-3"><div class="col-md-6"><label for="property-cover-upload" class="form-label">Image</label><input id="property-cover-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('coverUpload') is-invalid @enderror" wire:model="coverUpload">@error('coverUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label for="property-cover-alt" class="form-label">Alternative text</label><input id="property-cover-alt" type="text" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText">@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-9"><label for="property-cover-caption" class="form-label">Caption</label><input id="property-cover-caption" type="text" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption">@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">Replace cover</button></div></form></div></div>
                    </section>
                    <section class="pb-media-section" aria-labelledby="property-gallery-title"><div class="pb-section-heading"><div><h4 id="property-gallery-title">Gallery</h4><small>{{ number_format($gallery->count()) }} / {{ number_format(config('property-booking.media.property_gallery_limit', 12)) }} images</small></div></div>
                        <form wire:submit="addGalleryImage" class="row g-3 mb-4"><div class="col-md-4"><label for="property-gallery-upload" class="form-label">Image</label><input id="property-gallery-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('galleryUpload') is-invalid @enderror" wire:model="galleryUpload">@error('galleryUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-4"><label for="property-gallery-alt" class="form-label">Alternative text</label><input id="property-gallery-alt" type="text" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText">@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-3"><label for="property-gallery-caption" class="form-label">Caption</label><input id="property-gallery-caption" type="text" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption">@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-1 d-flex align-items-end"><button type="submit" class="btn btn-icon btn-primary" title="Add gallery image" aria-label="Add gallery image" wire:loading.attr="disabled"><i class="ti ti-plus"></i></button></div></form>
                        <div class="pb-gallery-grid">@forelse($gallery as $image)<article class="pb-gallery-item" wire:key="property-gallery-{{ $image->id }}"><img src="{{ $image->getUrl('thumb') }}" alt="{{ $image->getCustomProperty('alt_text', '') }}"><div class="pb-gallery-item__copy"><strong>{{ $image->getCustomProperty('alt_text', 'Image') }}</strong><small>{{ $image->getCustomProperty('caption', '') }}</small></div><div class="pb-gallery-item__actions"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'up')" title="Move earlier" aria-label="Move image earlier"><i class="ti ti-arrow-left"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'down')" title="Move later" aria-label="Move image later"><i class="ti ti-arrow-right"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $image->id }})" title="Edit metadata" aria-label="Edit image metadata"><i class="ti ti-edit"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeGalleryImage({{ $image->id }})" wire:confirm="Remove this gallery image?" title="Remove image" aria-label="Remove image"><i class="ti ti-trash"></i></button></div></article>@empty<div class="pb-empty"><i class="ti ti-photo-off"></i><strong>No gallery images</strong></div>@endforelse</div>
                    </section>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-primary" wire:click="closeDialog">Done</button></div>
            </div></div>
        </div><div class="modal-backdrop fade show"></div>
    @elseif ($dialog === 'metadata')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="property-metadata-title" wire:keydown.escape.window="closeMetadata">
            <div class="modal-dialog modal-md modal-dialog-centered"><form class="modal-content" wire:submit="saveMediaMetadata"><div class="modal-header"><h3 id="property-metadata-title" class="modal-title fs-18">Image metadata</h3><button type="button" class="btn-close" wire:click="closeMetadata" aria-label="Close metadata form"></button></div><div class="modal-body"><div class="mb-3"><label for="property-media-alt" class="form-label">Alternative text <span class="text-danger">*</span></label><input id="property-media-alt" type="text" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText">@error('mediaAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div><label for="property-media-caption" class="form-label">Caption</label><textarea id="property-media-caption" rows="3" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption"></textarea>@error('mediaCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeMetadata">Cancel</button><button type="submit" class="btn btn-primary">Save metadata</button></div></form></div>
        </div><div class="modal-backdrop fade show"></div>
    @endif
</div>
