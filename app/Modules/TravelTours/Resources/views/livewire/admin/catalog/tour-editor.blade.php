<section id="travel-tour-editor" aria-labelledby="travel-tour-editor-title">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('route')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <div class="card aureon-panel travel-tour-editor">
        <div class="card-header travel-editor-header">
            <div><h3 id="travel-tour-editor-title" class="card-title mb-1">{{ $this->currentTour?->name ?? 'New tour draft' }}</h3><p class="aureon-muted mb-0 fs-12">{{ $this->currentTour ? $this->currentTour->code : 'Create the tour before assigning its route' }}</p></div>
            @if($this->currentTour)<span class="travel-status {{ $this->currentTour->status->value === 'published' ? 'travel-status--active' : 'travel-status--muted' }}">{{ $this->currentTour->status->label() }}</span>@endif
        </div>
        <div class="travel-editor-tabs" role="tablist" aria-label="Tour editor sections">
            <button type="button" role="tab" aria-selected="{{ $tab === 'basics' ? 'true' : 'false' }}" class="travel-editor-tab {{ $tab === 'basics' ? 'is-active' : '' }}" wire:click="switchTab('basics')"><i class="ti ti-file-description" aria-hidden="true"></i>Basics</button>
            <button type="button" role="tab" aria-selected="{{ $tab === 'route' ? 'true' : 'false' }}" class="travel-editor-tab {{ $tab === 'route' ? 'is-active' : '' }}" wire:click="switchTab('route')" @disabled(!$this->currentTour)><i class="ti ti-route" aria-hidden="true"></i>Route</button>
            <button type="button" role="tab" aria-selected="{{ $tab === 'itinerary' ? 'true' : 'false' }}" class="travel-editor-tab {{ $tab === 'itinerary' ? 'is-active' : '' }}" wire:click="switchTab('itinerary')" @disabled(!$this->currentTour)><i class="ti ti-map-2" aria-hidden="true"></i>Itinerary</button>
            <button type="button" role="tab" aria-selected="{{ $tab === 'experience' ? 'true' : 'false' }}" class="travel-editor-tab {{ $tab === 'experience' ? 'is-active' : '' }}" wire:click="switchTab('experience')" @disabled(!$this->currentTour)><i class="ti ti-sparkles" aria-hidden="true"></i>Experience</button>
        </div>

        @if($tab === 'basics')
            <form wire:submit="saveBasics" class="travel-editor-form" novalidate>
                <section class="travel-editor-section" aria-labelledby="travel-identity-title">
                    <div class="travel-editor-section-heading"><span>01</span><div><h4 id="travel-identity-title">Identity and content</h4><p>The tour's stable code, public name, format, and description.</p></div></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="tour-code">Tour code</label><input id="tour-code" class="form-control @error('form.code') is-invalid @enderror" wire:model.blur="form.code" maxlength="40" placeholder="EGYPT-08D" required>@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-8"><label class="form-label" for="tour-name">Tour name</label><input id="tour-name" class="form-control @error('form.name') is-invalid @enderror" wire:model.blur="form.name" maxlength="200" required>@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="tour-slug">URL slug</label><input id="tour-slug" class="form-control @error('form.slug') is-invalid @enderror" wire:model.blur="form.slug" maxlength="180" placeholder="Generated from the name when blank">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label class="form-label" for="tour-type">Tour type</label><select id="tour-type" class="form-select" wire:model="form.type">@foreach(App\Modules\TravelTours\Catalog\Enums\TourType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('form.type')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label class="form-label" for="tour-mode">Booking mode</label><select id="tour-mode" class="form-select" wire:model="form.bookingMode">@foreach(App\Modules\TravelTours\Bookings\Enums\BookingMode::cases() as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach</select>@error('form.bookingMode')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="tour-tagline">Tagline</label><input id="tour-tagline" class="form-control" wire:model.blur="form.tagline" maxlength="240">@error('form.tagline')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="tour-summary">Catalog summary</label><textarea id="tour-summary" rows="3" class="form-control" wire:model.blur="form.shortDescription" maxlength="360"></textarea>@error('form.shortDescription')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="tour-description">Full description</label><textarea id="tour-description" rows="7" class="form-control" wire:model.blur="form.description"></textarea>@error('form.description')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    </div>
                </section>

                <section class="travel-editor-section" aria-labelledby="travel-profile-title">
                    <div class="travel-editor-section-heading"><span>02</span><div><h4 id="travel-profile-title">Operating profile</h4><p>Duration, accessibility of the experience, and participant limits.</p></div></div>
                    <div class="row g-3">
                        <div class="col-6 col-lg-3"><label class="form-label" for="tour-days">Days</label><input id="tour-days" type="number" min="1" max="365" class="form-control" wire:model="form.durationDays">@error('form.durationDays')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-6 col-lg-3"><label class="form-label" for="tour-nights">Nights</label><input id="tour-nights" type="number" min="0" max="365" class="form-control" wire:model="form.durationNights">@error('form.durationNights')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-6 col-lg-3"><label class="form-label" for="tour-age">Minimum age</label><input id="tour-age" type="number" min="0" max="120" class="form-control" wire:model="form.minimumAge">@error('form.minimumAge')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-6 col-lg-3"><label class="form-label" for="tour-difficulty">Difficulty</label><select id="tour-difficulty" class="form-select" wire:model="form.difficulty">@foreach(App\Modules\TravelTours\Catalog\Enums\TourDifficulty::cases() as $difficulty)<option value="{{ $difficulty->value }}">{{ $difficulty->label() }}</option>@endforeach</select>@error('form.difficulty')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="tour-minimum">Minimum participants</label><input id="tour-minimum" type="number" min="1" max="1000" class="form-control" wire:model="form.minimumParticipants">@error('form.minimumParticipants')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="tour-maximum">Maximum participants</label><input id="tour-maximum" type="number" min="1" max="1000" class="form-control" wire:model="form.maximumParticipants" placeholder="No limit">@error('form.maximumParticipants')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="tour-languages">Languages</label><input id="tour-languages" class="form-control" wire:model.blur="form.languages" placeholder="English, Swahili">@error('form.languages')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="tour-sort">Display order</label><input id="tour-sort" type="number" min="0" class="form-control" wire:model="form.sortOrder">@error('form.sortOrder')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-8 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="tour-featured" type="checkbox" class="form-check-input" wire:model="form.isFeatured"><label class="form-check-label" for="tour-featured">Feature in curated catalog areas</label></div></div>
                    </div>
                </section>

                <section class="travel-editor-section" aria-labelledby="travel-points-title">
                    <div class="travel-editor-section-heading"><span>03</span><div><h4 id="travel-points-title">Meeting and end points</h4><p>Traveler-facing directions and optional map coordinates.</p></div></div>
                    <div class="row g-3">
                        @foreach(['meeting' => 'Meeting', 'end' => 'End'] as $point => $label)
                            @php($nameField = $point.'PointName')
                            @php($detailsField = $point.'PointDetails')
                            <div class="col-lg-6"><label class="form-label" for="tour-{{ $point }}-name">{{ $label }} point</label><input id="tour-{{ $point }}-name" class="form-control" wire:model.blur="form.{{ $nameField }}">@error('form.'.$nameField)<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                            <div class="col-lg-3"><label class="form-label" for="tour-{{ $point }}-latitude">Latitude</label><input id="tour-{{ $point }}-latitude" class="form-control" wire:model.blur="form.{{ $point }}Latitude">@error('form.'.$point.'Latitude')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                            <div class="col-lg-3"><label class="form-label" for="tour-{{ $point }}-longitude">Longitude</label><input id="tour-{{ $point }}-longitude" class="form-control" wire:model.blur="form.{{ $point }}Longitude">@error('form.'.$point.'Longitude')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                            <div class="col-12"><label class="form-label" for="tour-{{ $point }}-details">{{ $label }} instructions</label><textarea id="tour-{{ $point }}-details" rows="3" class="form-control" wire:model.blur="form.{{ $detailsField }}"></textarea>@error('form.'.$detailsField)<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        @endforeach
                    </div>
                </section>

                <section class="travel-editor-section" aria-labelledby="travel-policy-title">
                    <div class="travel-editor-section-heading"><span>04</span><div><h4 id="travel-policy-title">Policy and search metadata</h4><p>Keep tour terms versioned and search descriptions purposeful.</p></div></div>
                    <div class="row g-3">
                        <div class="col-lg-8"><label class="form-label" for="tour-cancellation">Cancellation summary</label><textarea id="tour-cancellation" rows="4" class="form-control" wire:model.blur="form.cancellationSummary"></textarea>@error('form.cancellationSummary')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-lg-4"><label class="form-label" for="tour-policy-version">Policy version</label><input id="tour-policy-version" class="form-control" wire:model.blur="form.policyVersion" placeholder="2026-01">@error('form.policyVersion')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="tour-terms">Booking terms</label><textarea id="tour-terms" rows="5" class="form-control" wire:model.blur="form.terms"></textarea>@error('form.terms')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="tour-meta-title">SEO title</label><input id="tour-meta-title" class="form-control" wire:model.blur="form.metaTitle" maxlength="160">@error('form.metaTitle')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="tour-meta-description">SEO description</label><textarea id="tour-meta-description" rows="3" class="form-control" wire:model.blur="form.metaDescription" maxlength="320"></textarea>@error('form.metaDescription')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    </div>
                </section>
                <div class="travel-editor-actions"><a href="{{ route('travel-tours.admin.catalog.index') }}" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveBasics">{{ $this->currentTour ? 'Save basics' : 'Create tour draft' }}</button></div>
            </form>
        @elseif($tab === 'route')
            <form wire:submit="saveRoute" class="travel-editor-form" novalidate>
                <section class="travel-editor-section" aria-labelledby="travel-categories-title">
                    <div class="travel-editor-section-heading"><span>01</span><div><h4 id="travel-categories-title">Catalog categories</h4><p>Select discovery groups and one primary category.</p></div></div>
                    <div class="travel-category-grid">
                        @forelse($categoryOptions as $category)
                            <div class="form-check travel-choice" wire:key="category-option-{{ $category->id }}"><input id="category-option-{{ $category->id }}" type="checkbox" class="form-check-input" value="{{ $category->id }}" wire:model.live="assignments.categoryIds"><label class="form-check-label" for="category-option-{{ $category->id }}">{{ $category->name }}</label></div>
                        @empty<p class="aureon-muted">Create an active category before assigning one to this tour.</p>@endforelse
                    </div>
                    @error('assignments.categoryIds.*')<div class="text-danger fs-12">{{ $message }}</div>@enderror
                    <div class="travel-primary-category"><label class="form-label" for="tour-primary-category">Primary category</label><select id="tour-primary-category" class="form-select @error('assignments.primaryCategoryId') is-invalid @enderror" wire:model="assignments.primaryCategoryId"><option value="">Choose selected category</option>@foreach($categoryOptions->whereIn('id', array_map('intval', $assignments->categoryIds)) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('assignments.primaryCategoryId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </section>
                <section class="travel-editor-section" aria-labelledby="travel-route-title">
                    <div class="travel-editor-section-heading"><span>02</span><div><h4 id="travel-route-title">Destination route</h4><p>Arrange places in traveler-facing order and define the purpose of each stop.</p></div></div>
                    <div class="travel-route-add"><div><label class="form-label" for="tour-add-destination">Add destination</label><select id="tour-add-destination" class="form-select" wire:model="pendingDestinationId"><option value="">Choose destination</option>@foreach($destinationOptions as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select></div><button type="button" class="btn btn-outline-secondary" wire:click="addDestination"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add to route</button></div>
                    <div class="travel-route-list">
                        @forelse($assignments->destinationRows as $index => $row)
                            @php($destination = $destinationOptions->firstWhere('id', (int) $row['destination_id']))
                            <article class="travel-route-row" wire:key="route-row-{{ $row['destination_id'] }}">
                                <span class="travel-route-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <strong class="travel-route-name">{{ $destination?->name ?? 'Unavailable destination' }}</strong>
                                <div><label class="form-label" for="route-role-{{ $index }}">Role</label><select id="route-role-{{ $index }}" class="form-select" wire:model="assignments.destinationRows.{{ $index }}.role"><option value="primary">Primary</option><option value="start">Start</option><option value="visit">Visit</option><option value="overnight">Overnight</option><option value="end">End</option></select></div>
                                <div class="form-check form-switch"><input id="route-overnight-{{ $index }}" type="checkbox" class="form-check-input" wire:model="assignments.destinationRows.{{ $index }}.is_overnight"><label class="form-check-label" for="route-overnight-{{ $index }}">Overnight</label></div>
                                <div class="travel-route-actions"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveDestination({{ $index }}, 'up')" @disabled($index === 0) aria-label="Move {{ $destination?->name }} earlier"><i class="ti ti-arrow-up" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveDestination({{ $index }}, 'down')" @disabled($index === count($assignments->destinationRows) - 1) aria-label="Move {{ $destination?->name }} later"><i class="ti ti-arrow-down" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeDestination({{ $index }})" aria-label="Remove {{ $destination?->name }}"><i class="ti ti-trash" aria-hidden="true"></i></button></div>
                            </article>
                        @empty<div class="travel-admin-empty"><i class="ti ti-map-route" aria-hidden="true"></i><strong>No destinations assigned</strong><span>Add a place to start the route.</span></div>@endforelse
                    </div>
                    @error('assignments.destinationRows.*.destination_id')<div class="text-danger fs-12">{{ $message }}</div>@enderror
                </section>
                <div class="travel-editor-actions"><button type="button" class="btn btn-outline-secondary" wire:click="switchTab('basics')">Back to basics</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveRoute">Save route</button></div>
            </form>
        @elseif($tab === 'itinerary')
            <livewire:travel-tours.admin.tour-itinerary-editor :tour-id="$tourId" :key="'tour-itinerary-'.$tourId" />
        @else
            <livewire:travel-tours.admin.tour-experience-editor :tour-id="$tourId" :key="'tour-experience-'.$tourId" />
        @endif
    </div>
</section>
