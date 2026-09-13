<div id="institution-details-editor">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="row g-4">
        <div class="col-xl-8">
            <section class="card aureon-panel mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Organization identity</h5></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label for="institution-name" class="form-label">Institution name</label>
                        <input id="institution-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.live.blur="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="institution-short-name" class="form-label">Short name</label>
                        <input id="institution-short-name" type="text" class="form-control @error('form.shortName') is-invalid @enderror" wire:model.live.blur="form.shortName">
                        @error('form.shortName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="institution-descriptor" class="form-label">Descriptor</label>
                        <input id="institution-descriptor" type="text" class="form-control @error('form.descriptor') is-invalid @enderror" wire:model.live.blur="form.descriptor">
                        @error('form.descriptor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </section>

            <section class="card aureon-panel mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Contact information</h5></div>
                <div class="card-body row g-3">
                    @foreach ([['primaryEmail', 'Primary email', 'email'], ['secondaryEmail', 'Secondary email', 'email'], ['primaryPhone', 'Primary phone', 'text'], ['secondaryPhone', 'Secondary phone', 'text']] as [$field, $label, $type])
                        <div class="col-md-6">
                            <label for="institution-{{ $field }}" class="form-label">{{ $label }}</label>
                            <input id="institution-{{ $field }}" type="{{ $type }}" class="form-control @error('form.'.$field) is-invalid @enderror" wire:model.live.blur="form.{{ $field }}">
                            @error('form.'.$field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                    <div class="col-12">
                        <label for="institution-website" class="form-label">Website</label>
                        <input id="institution-website" type="url" class="form-control @error('form.website') is-invalid @enderror" wire:model.live.blur="form.website">
                        @error('form.website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </section>

            <section class="card aureon-panel mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Address</h5></div>
                <div class="card-body row g-3">
                    @foreach ([['physicalAddress', 'Physical address', 'col-12'], ['city', 'City or town', 'col-md-6'], ['county', 'County, state, or province', 'col-md-6'], ['postalAddress', 'Postal address', 'col-md-6'], ['postalCity', 'Postal city', 'col-md-4'], ['postalCode', 'Postal code', 'col-md-2']] as [$field, $label, $column])
                        <div class="{{ $column }}">
                            <label for="institution-{{ $field }}" class="form-label">{{ $label }}</label>
                            <input id="institution-{{ $field }}" type="text" class="form-control @error('form.'.$field) is-invalid @enderror" wire:model.live.blur="form.{{ $field }}">
                            @error('form.'.$field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card aureon-panel">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Social profiles</h5>
                    @can(\App\Support\CmsPermission::MANAGE_INSTITUTION_DETAILS)
                        <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="addSocialMedia" title="Add social profile" aria-label="Add social profile"><i class="ti ti-plus"></i></button>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($form->socialMedia as $index => $social)
                        <div class="row g-2 align-items-start mb-3" wire:key="social-{{ $index }}">
                            <div class="col-md-3"><input type="text" class="form-control" placeholder="Platform" wire:model.live.blur="form.socialMedia.{{ $index }}.platform"></div>
                            <div class="col-md-3"><input type="text" class="form-control" placeholder="Handle" wire:model.live.blur="form.socialMedia.{{ $index }}.handle"></div>
                            <div class="col-md-5"><input type="url" class="form-control @error('form.socialMedia.'.$index.'.url') is-invalid @enderror" placeholder="https://" wire:model.live.blur="form.socialMedia.{{ $index }}.url">@error('form.socialMedia.'.$index.'.url') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-1 text-end"><button type="button" class="btn btn-icon btn-outline-danger" wire:click="removeSocialMedia({{ $index }})" title="Remove social profile" aria-label="Remove social profile"><i class="ti ti-trash"></i></button></div>
                        </div>
                    @empty
                        <p class="aureon-muted mb-0">No social profiles configured.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card aureon-panel mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Brand assets</h5></div>
                <div class="card-body">
                    @foreach ([['mainLogoUpload', 'main_logo', 'Main logo', $this->profile->mainLogoUrl, $this->profile->hasCustomMainLogo], ['logoIconUpload', 'logo_icon', 'Logo icon', $this->profile->logoIconUrl, $this->profile->hasCustomLogoIcon]] as [$upload, $collection, $label, $currentUrl, $hasCustomLogo])
                        <div class="aureon-brand-upload mb-4">
                            <div class="aureon-brand-preview mb-3">
                                <img src="{{ $this->{$upload} ? $this->{$upload}->temporaryUrl() : $currentUrl }}" alt="{{ $label }} preview">
                            </div>
                            <label for="{{ $upload }}" class="form-label">{{ $label }}</label>
                            <input id="{{ $upload }}" type="file" class="form-control @error($upload) is-invalid @enderror" wire:model="{{ $upload }}" accept="image/png,image/jpeg">
                            @error($upload) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if ($hasCustomLogo)
                                <button type="button" class="btn btn-sm btn-link text-danger px-0 mt-2" wire:click="removeLogo('{{ $collection }}')">Remove custom {{ strtolower($label) }}</button>
                            @endif
                        </div>
                    @endforeach
                    <p class="aureon-muted fs-12 mb-0">PNG or JPEG. Mail and PDF templates fall back to the centralized Aureon brand kit.</p>
                </div>
            </section>

            <section class="card aureon-panel mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Communication preview</h5></div>
                <div class="card-body">
                    <h6 class="mb-1">{{ $form->name ?: 'Institution name' }}</h6>
                    <p class="aureon-muted mb-3">{{ $form->descriptor ?: 'Institution descriptor' }}</p>
                    <p class="mb-1">{{ $form->primaryEmail ?: 'No primary email' }}</p>
                    <p class="mb-0">{{ $form->primaryPhone ?: 'No primary phone' }}</p>
                </div>
            </section>

            @can(\App\Support\CmsPermission::MANAGE_INSTITUTION_DETAILS)
                <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="save,mainLogoUpload,logoIconUpload">
                    <i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">Save institution details</span><span wire:loading wire:target="save">Saving...</span>
                </button>
                <p class="aureon-unsaved mt-2 mb-0 text-center" wire:dirty>Unsaved changes</p>
            @endcan
        </div>
    </form>
</div>
