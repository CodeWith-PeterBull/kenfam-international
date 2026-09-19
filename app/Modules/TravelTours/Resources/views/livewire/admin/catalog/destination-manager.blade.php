@use('App\Modules\TravelTours\Catalog\Enums\DestinationType')
@use('App\Modules\TravelTours\Catalog\Enums\PublicationStatus')
@use('App\Modules\TravelTours\Catalog\Models\Destination')
@php
    $user = auth()->user();
    $canCreate = $user->can('create', Destination::class);
    $destinations = $this->destinations;
    $selected = $this->selectedDestination;
    $statusClass = static fn (PublicationStatus $status): string => match ($status) {
        PublicationStatus::Published => 'travel-status--active',
        PublicationStatus::Review => 'travel-status--review',
        default => 'travel-status--muted',
    };
@endphp

<div id="travel-destination-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-destination-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-destination-title" class="card-title mb-1">Destinations</h3>
                <p class="aureon-muted mb-0">{{ number_format($destinations->total()) }} {{ Str::plural('record', $destinations->total()) }} in the geographic hierarchy, ordered as the storefront lists them</p>
            </div>
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="openCreate">
                    <i class="ti ti-map-pin-plus me-2" aria-hidden="true"></i>Add destination
                </button>
            @endif
        </div>

        <div class="card-body border-bottom">
            <div class="travel-admin-filters">
                <div class="travel-admin-filter travel-admin-filter--search">
                    <label for="travel-destination-search" class="form-label">Search destinations</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
                        <input id="travel-destination-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Name, code, or country">
                    </div>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-destination-type-filter" class="form-label">Type</label>
                    <select id="travel-destination-type-filter" class="form-select" wire:model.live="typeFilter">
                        <option value="">All types</option>
                        @foreach (DestinationType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-destination-status-filter" class="form-label">Status</label>
                    <select id="travel-destination-status-filter" class="form-select" wire:model.live="statusFilter">
                        <option value="">All states</option>
                        @foreach (PublicationStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter travel-admin-filter--action">
                    <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button>
                </div>
            </div>
        </div>
        <div class="travel-catalog-status-counts" aria-label="Destinations by state">
            @foreach ($this->statusCounts as $value => $count)
                <span><strong>{{ number_format($count) }}</strong>{{ PublicationStatus::from($value)->label() }}</span>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col" class="travel-admin-index">#</th>
                        <th scope="col">Destination</th>
                        <th scope="col">Hierarchy</th>
                        <th scope="col">Catalog use</th>
                        <th scope="col">Active</th>
                        <th scope="col">Published</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($destinations as $destination)
                        @php
                            $cover = $destination->getFirstMedia('destination_cover');
                            $canUpdate = $user->can('update', $destination);
                            $isPublished = $destination->status === PublicationStatus::Published;
                            $canPublish = $user->can($isPublished ? 'unpublish' : 'publish', $destination);
                            $activeBlocker = match (true) {
                                ! $destination->is_active => null,
                                $isPublished => 'Unpublish it before hiding',
                                $destination->active_children_count > 0 => 'Hide its active child destinations first',
                                $destination->published_tours_count > 0 => trans_choice('Visited by :count published tour|Visited by :count published tours', $destination->published_tours_count, ['count' => $destination->published_tours_count]),
                                default => null,
                            };
                            $publishBlocker = match (true) {
                                $destination->status === PublicationStatus::Archived => 'Archived',
                                $isPublished && $destination->published_tours_count > 0 => trans_choice('Visited by :count published tour|Visited by :count published tours', $destination->published_tours_count, ['count' => $destination->published_tours_count]),
                                $isPublished && $destination->published_children_count > 0 => 'Unpublish its published child destinations first',
                                ! $isPublished && ! $destination->is_active => 'Activate it first',
                                ! $isPublished && $destination->parent && $destination->parent->status !== PublicationStatus::Published => 'Publish its parent first',
                                default => null,
                            };
                        @endphp
                        <tr wire:key="travel-destination-{{ $destination->id }}">
                            <td class="travel-admin-index">{{ $destinations->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($cover)
                                        <img class="travel-admin-thumb" src="{{ $cover->hasGeneratedConversion('thumb') ? $cover->getUrl('thumb') : $cover->getUrl() }}" alt="{{ $cover->getCustomProperty('alt_text', '') }}">
                                    @else
                                        <span class="travel-admin-thumb travel-admin-thumb--empty"><i class="ti ti-photo" aria-hidden="true"></i></span>
                                    @endif
                                    <div class="min-w-0">
                                        <strong class="d-block text-break">{{ $destination->name }}</strong>
                                        <small class="aureon-muted">{{ $destination->code ?: $destination->slug }}@if ($destination->is_featured) &middot; featured @endif</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="travel-status travel-status--muted">{{ $destination->type->label() }}</span>
                                <small class="d-block aureon-muted mt-1">{{ $destination->parent?->name ?? 'Top level' }}@if ($destination->country_code) &middot; {{ $destination->country_code }}@endif</small>
                            </td>
                            <td>
                                {{ trans_choice(':count tour|:count tours', $destination->tours_count, ['count' => number_format($destination->tours_count)]) }}
                                <small class="d-block aureon-muted">{{ trans_choice(':count child destination|:count child destinations', $destination->children_count, ['count' => number_format($destination->children_count)]) }}</small>
                            </td>
                            <td>
                                @if ($canUpdate)
                                    <div class="form-check form-switch travel-admin-switch">
                                        {{-- .prevent stops the browser's optimistic flip so a refused change never leaves the switch out of step with the server. --}}
                                        <input id="travel-destination-active-{{ $destination->id }}" type="checkbox" role="switch"
                                            wire:key="travel-destination-active-{{ $destination->id }}-{{ $destination->is_active ? 'on' : 'off' }}"
                                            class="form-check-input"
                                            wire:click.prevent="toggleActive({{ $destination->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleActive"
                                            @checked($destination->is_active)
                                            @if ($activeBlocker) aria-describedby="travel-destination-active-hint-{{ $destination->id }}" @endif>
                                        <label for="travel-destination-active-{{ $destination->id }}" class="form-check-label">{{ $destination->is_active ? 'Active' : 'Hidden' }}</label>
                                    </div>
                                    @if ($activeBlocker)
                                        <small id="travel-destination-active-hint-{{ $destination->id }}" class="d-block aureon-muted travel-admin-switch-hint"><i class="ti ti-lock" aria-hidden="true"></i>{{ $activeBlocker }}</small>
                                    @endif
                                @else
                                    <span class="travel-status {{ $destination->is_active ? 'travel-status--active' : 'travel-status--muted' }}">{{ $destination->is_active ? 'Active' : 'Hidden' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($canPublish && $destination->status !== PublicationStatus::Archived)
                                    <div class="form-check form-switch travel-admin-switch">
                                        <input id="travel-destination-published-{{ $destination->id }}" type="checkbox" role="switch"
                                            wire:key="travel-destination-published-{{ $destination->id }}-{{ $isPublished ? 'on' : 'off' }}"
                                            class="form-check-input"
                                            wire:click.prevent="togglePublished({{ $destination->id }})"
                                            wire:loading.attr="disabled" wire:target="togglePublished"
                                            @checked($isPublished)
                                            @if ($publishBlocker) aria-describedby="travel-destination-published-hint-{{ $destination->id }}" @endif>
                                        <label for="travel-destination-published-{{ $destination->id }}" class="form-check-label">{{ $destination->status->label() }}</label>
                                    </div>
                                    @if ($publishBlocker)
                                        <small id="travel-destination-published-hint-{{ $destination->id }}" class="d-block aureon-muted travel-admin-switch-hint"><i class="ti ti-lock" aria-hidden="true"></i>{{ $publishBlocker }}</small>
                                    @endif
                                @else
                                    <span class="travel-status {{ $statusClass($destination->status) }}">{{ $destination->status->label() }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="travel-admin-row-actions justify-content-end">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openDetails({{ $destination->id }})" title="View destination" aria-label="View {{ $destination->name }}">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </button>
                                    @if ($canUpdate)
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $destination->id }})" title="Edit destination" aria-label="Edit {{ $destination->name }}">
                                            <i class="ti ti-edit" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="travel-admin-empty">
                                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                                    <strong>No destinations match the current filters</strong>
                                    <span>Adjust the search or add a destination to the hierarchy.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($destinations->hasPages())
            <div class="card-footer">{{ $destinations->links() }}</div>
        @endif
    </section>

    @if ($dialog === 'form')
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-destination-form-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('#travel-destination-name')?.focus())">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save" novalidate>
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-destination-form-title" class="modal-title fs-18">{{ $selectedDestinationId ? 'Edit destination' : 'Add destination' }}</h3>
                            <p class="aureon-muted fs-12 mb-0">Editorial metadata only; publication is the switch in the table.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close destination form"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="travel-destination-type" class="form-label">Type</label>
                                <select id="travel-destination-type" class="form-select @error('form.type') is-invalid @enderror" wire:model="form.type"
                                    @error('form.type') aria-invalid="true" aria-describedby="travel-destination-type-error" @enderror>
                                    @foreach (DestinationType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('form.type')<div id="travel-destination-type-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label for="travel-destination-name" class="form-label">Name</label>
                                <input id="travel-destination-name" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" required
                                    @error('form.name') aria-invalid="true" aria-describedby="travel-destination-name-error" @enderror>
                                @error('form.name')<div id="travel-destination-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-destination-slug" class="form-label">URL slug</label>
                                <input id="travel-destination-slug" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="Generated from name"
                                    @error('form.slug') aria-invalid="true" aria-describedby="travel-destination-slug-error" @enderror>
                                @error('form.slug')<div id="travel-destination-slug-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-destination-parent" class="form-label">Parent destination</label>
                                <select id="travel-destination-parent" class="form-select @error('form.parentId') is-invalid @enderror" wire:model="form.parentId"
                                    @error('form.parentId') aria-invalid="true" aria-describedby="travel-destination-parent-error" @enderror>
                                    <option value="">Top level</option>
                                    @foreach ($this->parentOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }} &middot; {{ $option->type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('form.parentId')<div id="travel-destination-parent-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label for="travel-destination-country" class="form-label">Country code</label>
                                <input id="travel-destination-country" maxlength="2" class="form-control text-uppercase @error('form.countryCode') is-invalid @enderror" wire:model="form.countryCode" placeholder="KE"
                                    @error('form.countryCode') aria-invalid="true" aria-describedby="travel-destination-country-error" @enderror>
                                @error('form.countryCode')<div id="travel-destination-country-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label for="travel-destination-code" class="form-label">Operational code</label>
                                <input id="travel-destination-code" class="form-control text-uppercase @error('form.code') is-invalid @enderror" wire:model="form.code" placeholder="NBO"
                                    @error('form.code') aria-invalid="true" aria-describedby="travel-destination-code-error" @enderror>
                                @error('form.code')<div id="travel-destination-code-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="travel-destination-timezone" class="form-label">IANA timezone</label>
                                <input id="travel-destination-timezone" class="form-control @error('form.timezone') is-invalid @enderror" wire:model="form.timezone" placeholder="Africa/Nairobi"
                                    @error('form.timezone') aria-invalid="true" aria-describedby="travel-destination-timezone-error" @enderror>
                                @error('form.timezone')<div id="travel-destination-timezone-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label for="travel-destination-order" class="form-label">Sort order</label>
                                <input id="travel-destination-order" type="number" min="0" class="form-control @error('form.sortOrder') is-invalid @enderror" wire:model="form.sortOrder"
                                    @error('form.sortOrder') aria-invalid="true" aria-describedby="travel-destination-order-error" @enderror>
                                @error('form.sortOrder')<div id="travel-destination-order-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="travel-destination-summary" class="form-label">Short description</label>
                                <textarea id="travel-destination-summary" rows="2" class="form-control @error('form.shortDescription') is-invalid @enderror" wire:model="form.shortDescription"
                                    @error('form.shortDescription') aria-invalid="true" aria-describedby="travel-destination-summary-error" @enderror></textarea>
                                @error('form.shortDescription')<div id="travel-destination-summary-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="travel-destination-description" class="form-label">Destination narrative</label>
                                <textarea id="travel-destination-description" rows="5" class="form-control @error('form.description') is-invalid @enderror" wire:model="form.description"
                                    @error('form.description') aria-invalid="true" aria-describedby="travel-destination-description-error" @enderror></textarea>
                                @error('form.description')<div id="travel-destination-description-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="travel-destination-latitude" class="form-label">Latitude</label>
                                <input id="travel-destination-latitude" inputmode="decimal" class="form-control @error('form.latitude') is-invalid @enderror" wire:model="form.latitude"
                                    @error('form.latitude') aria-invalid="true" aria-describedby="travel-destination-latitude-error" @enderror>
                                @error('form.latitude')<div id="travel-destination-latitude-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="travel-destination-longitude" class="form-label">Longitude</label>
                                <input id="travel-destination-longitude" inputmode="decimal" class="form-control @error('form.longitude') is-invalid @enderror" wire:model="form.longitude"
                                    @error('form.longitude') aria-invalid="true" aria-describedby="travel-destination-longitude-error" @enderror>
                                @error('form.longitude')<div id="travel-destination-longitude-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="travel-destination-status" class="form-label">Editorial state</label>
                                @if (in_array($form->status, ['published', 'archived'], true))
                                    <select id="travel-destination-status" class="form-select" disabled>
                                        <option>{{ PublicationStatus::from($form->status)->label() }}</option>
                                    </select>
                                    <small class="aureon-muted">Changed with the Published switch in the table.</small>
                                @else
                                    <select id="travel-destination-status" class="form-select @error('form.status') is-invalid @enderror" wire:model="form.status"
                                        @error('form.status') aria-invalid="true" aria-describedby="travel-destination-status-error" @enderror>
                                        <option value="draft">Draft</option>
                                        <option value="review">Review</option>
                                    </select>
                                    @error('form.status')<div id="travel-destination-status-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label for="travel-destination-meta-title" class="form-label">SEO title</label>
                                <input id="travel-destination-meta-title" class="form-control @error('form.metaTitle') is-invalid @enderror" wire:model="form.metaTitle"
                                    @error('form.metaTitle') aria-invalid="true" aria-describedby="travel-destination-meta-title-error" @enderror>
                                @error('form.metaTitle')<div id="travel-destination-meta-title-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="travel-destination-meta-description" class="form-label">SEO description</label>
                                <textarea id="travel-destination-meta-description" rows="2" class="form-control @error('form.metaDescription') is-invalid @enderror" wire:model="form.metaDescription"
                                    @error('form.metaDescription') aria-invalid="true" aria-describedby="travel-destination-meta-description-error" @enderror></textarea>
                                @error('form.metaDescription')<div id="travel-destination-meta-description-error" class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-4">
                                <div class="form-check form-switch">
                                    <input id="travel-destination-active" type="checkbox" role="switch" class="form-check-input" wire:model="form.isActive">
                                    <label for="travel-destination-active" class="form-check-label">Active for assignment</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input id="travel-destination-featured" type="checkbox" role="switch" class="form-check-input" wire:model="form.isFeatured">
                                    <label for="travel-destination-featured" class="form-check-label">Featured placement</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save destination</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'details' && $selected)
        @php
            $cover = $selected->getFirstMedia('destination_cover');
            $gallery = $selected->getMedia('destination_gallery');
            $canEditMedia = $user->can('update', $selected);
        @endphp
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-destination-detail-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('.btn-close')?.focus())">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-destination-detail-title" class="modal-title fs-18">{{ $selected->name }}</h3>
                            <p class="aureon-muted fs-12 mb-0">
                                {{ $selected->type->label() }}
                                &middot; {{ $selected->parent ? 'under '.$selected->parent->name : 'top level' }}
                                &middot; {{ strtolower($selected->status->label()) }}{{ $selected->is_active ? '' : ', hidden' }}
                            </p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close destination details"></button>
                    </div>
                    <div class="modal-body">
                        @error('media')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="travel-detail-grid">
                            <section aria-labelledby="travel-destination-detail-identity">
                                <h4 id="travel-destination-detail-identity">Identity</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>URL slug</dt><dd><code>{{ $selected->slug }}</code></dd></div>
                                    <div><dt>Operational code</dt><dd>{{ $selected->code ?: 'Not set' }}</dd></div>
                                    <div><dt>Country</dt><dd>{{ $selected->country_code ?: 'Not set' }}</dd></div>
                                    <div><dt>Timezone</dt><dd>{{ $selected->timezone ?: 'Not set' }}</dd></div>
                                    <div><dt>Coordinates</dt><dd>{{ $selected->latitude !== null && $selected->longitude !== null ? $selected->latitude.', '.$selected->longitude : 'Not set' }}</dd></div>
                                    <div><dt>Sort order</dt><dd>{{ number_format($selected->sort_order) }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-destination-detail-publication">
                                <h4 id="travel-destination-detail-publication">Publication</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Status</dt><dd><span class="travel-status {{ $statusClass($selected->status) }}">{{ $selected->status->label() }}</span></dd></div>
                                    <div><dt>Published</dt><dd>{{ $selected->published_at?->format('d M Y, H:i') ?: 'Not yet' }}</dd></div>
                                    <div><dt>Availability</dt><dd>{{ $selected->is_active ? 'Active for assignment' : 'Hidden from assignment' }}</dd></div>
                                    <div><dt>Placement</dt><dd>{{ $selected->is_featured ? 'Featured' : 'Standard' }}</dd></div>
                                    <div><dt>Created</dt><dd>{{ $selected->created_at?->format('d M Y, H:i') ?: 'Unknown' }}</dd></div>
                                    <div><dt>Updated</dt><dd>{{ $selected->updated_at?->format('d M Y, H:i') ?: 'Unknown' }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-destination-detail-seo">
                                <h4 id="travel-destination-detail-seo">Search presentation</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>SEO title</dt><dd>{{ $selected->meta_title ?: 'Falls back to the name' }}</dd></div>
                                    <div><dt>SEO description</dt><dd>{{ $selected->meta_description ?: 'Not set' }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-destination-detail-copy">
                                <h4 id="travel-destination-detail-copy">Editorial copy</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Short description</dt><dd>{{ $selected->short_description ?: 'Not set' }}</dd></div>
                                    <div><dt>Narrative</dt><dd>{{ $selected->description ?: 'Not set' }}</dd></div>
                                </dl>
                            </section>

                            <section class="travel-detail-grid__wide" aria-labelledby="travel-destination-detail-images">
                                <h4 id="travel-destination-detail-images">Images</h4>
                                <div class="row g-4">
                                    <div class="col-lg-5">
                                        <h5 class="fs-13 fw-bold mb-2">Cover</h5>
                                        @if ($cover)
                                            <figure class="travel-admin-media-item mb-3">
                                                <img src="{{ $cover->hasGeneratedConversion('card') ? $cover->getUrl('card') : $cover->getUrl() }}" alt="{{ $cover->getCustomProperty('alt_text', '') }}">
                                                <figcaption><p>{{ $cover->getCustomProperty('alt_text', '') }}</p></figcaption>
                                                @if ($canEditMedia)
                                                    <div class="travel-admin-media-actions">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $cover->id }})">Edit text</button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeCover" wire:confirm="Remove this destination cover?">Remove cover</button>
                                                    </div>
                                                @endif
                                            </figure>
                                        @else
                                            <p class="aureon-muted">No cover image yet.</p>
                                        @endif
                                        @if ($canEditMedia)
                                            <form wire:submit="replaceCover" class="row g-2" novalidate>
                                                <div class="col-12">
                                                    <label for="travel-cover-upload" class="form-label">{{ $cover ? 'Replace cover' : 'Add cover' }}</label>
                                                    <input id="travel-cover-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('coverUpload') is-invalid @enderror" wire:model="coverUpload">
                                                    @error('coverUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                @if ($coverUpload && method_exists($coverUpload, 'temporaryUrl'))
                                                    <div class="col-12">
                                                        <figure class="travel-admin-upload-preview"><img src="{{ $coverUpload->temporaryUrl() }}" alt="{{ $coverAltText ?: 'Selected cover preview' }}"><figcaption>{{ $coverAltText }}</figcaption></figure>
                                                    </div>
                                                @endif
                                                <div class="col-12">
                                                    <label for="travel-cover-alt" class="form-label">Alternative text</label>
                                                    <input id="travel-cover-alt" class="form-control @error('coverAltText') is-invalid @enderror" wire:model.blur="coverAltText" maxlength="180">
                                                    @error('coverAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div class="col-12">
                                                    <label for="travel-cover-caption" class="form-label">Caption</label>
                                                    <input id="travel-cover-caption" class="form-control @error('coverCaption') is-invalid @enderror" wire:model.blur="coverCaption" maxlength="320">
                                                    @error('coverCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="replaceCover,coverUpload">Save cover</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="d-flex justify-content-between align-items-baseline gap-3 mb-2">
                                            <h5 class="fs-13 fw-bold mb-0">Gallery</h5>
                                            <small class="aureon-muted">{{ number_format($gallery->count()) }} / {{ config('travel-tours.media.destination_gallery_limit', 12) }} images</small>
                                        </div>
                                        <div class="travel-admin-media-grid mb-3">
                                            @forelse ($gallery as $image)
                                                <figure class="travel-admin-media-item" wire:key="travel-gallery-{{ $image->id }}">
                                                    <img src="{{ $image->hasGeneratedConversion('thumb') ? $image->getUrl('thumb') : $image->getUrl() }}" alt="{{ $image->getCustomProperty('alt_text', '') }}">
                                                    <figcaption><p>{{ $image->getCustomProperty('alt_text', '') }}</p></figcaption>
                                                    @if ($canEditMedia)
                                                        <div class="travel-admin-media-actions">
                                                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openMediaMetadata({{ $image->id }})" aria-label="Edit image text" title="Edit text"><i class="ti ti-pencil" aria-hidden="true"></i></button>
                                                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'up')" aria-label="Move image earlier" title="Move earlier"><i class="ti ti-arrow-left" aria-hidden="true"></i></button>
                                                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveGalleryImage({{ $image->id }}, 'down')" aria-label="Move image later" title="Move later"><i class="ti ti-arrow-right" aria-hidden="true"></i></button>
                                                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeGalleryImage({{ $image->id }})" wire:confirm="Remove this gallery image?" aria-label="Remove image" title="Remove image"><i class="ti ti-trash" aria-hidden="true"></i></button>
                                                        </div>
                                                    @endif
                                                </figure>
                                            @empty
                                                <p class="aureon-muted mb-0">No gallery images yet.</p>
                                            @endforelse
                                        </div>
                                        @if ($canEditMedia)
                                            <form wire:submit="addGalleryImage" class="row g-2" novalidate>
                                                <div class="col-12">
                                                    <label for="travel-gallery-upload" class="form-label">Add gallery image</label>
                                                    <input id="travel-gallery-upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('galleryUpload') is-invalid @enderror" wire:model="galleryUpload">
                                                    @error('galleryUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                @if ($galleryUpload && method_exists($galleryUpload, 'temporaryUrl'))
                                                    <div class="col-12">
                                                        <figure class="travel-admin-upload-preview"><img src="{{ $galleryUpload->temporaryUrl() }}" alt="{{ $galleryAltText ?: 'Selected gallery preview' }}"><figcaption>{{ $galleryAltText }}</figcaption></figure>
                                                    </div>
                                                @endif
                                                <div class="col-md-6">
                                                    <label for="travel-gallery-alt" class="form-label">Alternative text</label>
                                                    <input id="travel-gallery-alt" class="form-control @error('galleryAltText') is-invalid @enderror" wire:model.blur="galleryAltText" maxlength="180">
                                                    @error('galleryAltText')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="travel-gallery-caption" class="form-label">Caption</label>
                                                    <input id="travel-gallery-caption" class="form-control @error('galleryCaption') is-invalid @enderror" wire:model.blur="galleryCaption" maxlength="320">
                                                    @error('galleryCaption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="addGalleryImage,galleryUpload">Add image</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </section>

                            <section class="travel-detail-grid__wide" aria-labelledby="travel-destination-detail-children">
                                <h4 id="travel-destination-detail-children">Child destinations ({{ number_format($selected->children->count()) }})</h4>
                                @if ($selected->children->isEmpty())
                                    <p class="aureon-muted mb-0">No child destinations.</p>
                                @else
                                    <ul class="travel-detail-chips">
                                        @foreach ($selected->children as $child)
                                            <li><span class="travel-status {{ $child->is_active ? 'travel-status--active' : 'travel-status--muted' }}">{{ $child->name }} &middot; {{ $child->status->label() }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-destination-detail-tours">
                                <h4 id="travel-destination-detail-tours">Tours visiting ({{ number_format($selected->tours->count()) }})</h4>
                                @if ($selected->tours->isEmpty())
                                    <p class="aureon-muted mb-0">No tours visit this destination yet.</p>
                                @else
                                    <ul class="travel-detail-rows">
                                        @foreach ($selected->tours as $tour)
                                            <li>
                                                <div class="min-w-0">
                                                    <strong class="d-block text-break">{{ $tour->name }}</strong>
                                                    <small class="aureon-muted">{{ $tour->code }}</small>
                                                </div>
                                                <span class="travel-status {{ $statusClass($tour->status) }}">{{ $tour->status->label() }}</span>
                                                @can('update', $tour)
                                                    <a class="btn btn-icon btn-sm btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.tours.edit', $tour) }}" title="Open tour" aria-label="Open {{ $tour->name }} in the tour editor">
                                                        <i class="ti ti-external-link" aria-hidden="true"></i>
                                                    </a>
                                                @endcan
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        @can('update', $selected)
                            <button type="button" class="btn btn-outline-secondary" wire:click="openEdit({{ $selected->id }})">
                                <i class="ti ti-edit me-2" aria-hidden="true"></i>Edit destination
                            </button>
                        @endcan
                        <button type="button" class="btn btn-primary" wire:click="closeDialog">Done</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    @if ($dialog === 'metadata' && $selected)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-media-metadata-title" wire:keydown.escape.window="closeMetadata"
            x-data x-init="$nextTick(() => $el.querySelector('#travel-media-alt')?.focus())">
            <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                <form class="modal-content" wire:submit="saveMediaMetadata" novalidate>
                    <div class="modal-header">
                        <h3 id="travel-media-metadata-title" class="modal-title fs-18">Edit image text</h3>
                        <button type="button" class="btn-close" wire:click="closeMetadata" aria-label="Close image text form"></button>
                    </div>
                    <div class="modal-body">
                        @error('media')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label for="travel-media-alt" class="form-label">Alternative text</label>
                            <input id="travel-media-alt" class="form-control @error('mediaAltText') is-invalid @enderror" wire:model="mediaAltText" required
                                @error('mediaAltText') aria-invalid="true" aria-describedby="travel-media-alt-error" @enderror>
                            @error('mediaAltText')<div id="travel-media-alt-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="travel-media-caption" class="form-label">Caption</label>
                            <textarea id="travel-media-caption" rows="3" class="form-control @error('mediaCaption') is-invalid @enderror" wire:model="mediaCaption"
                                @error('mediaCaption') aria-invalid="true" aria-describedby="travel-media-caption-error" @enderror></textarea>
                            @error('mediaCaption')<div id="travel-media-caption-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeMetadata">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveMediaMetadata">Save text</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
