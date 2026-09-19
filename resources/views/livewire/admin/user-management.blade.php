<div id="user-management">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    @php
        $userStats = [
            ['label' => 'Total accounts', 'value' => $this->statistics['total'], 'icon' => 'ti-users', 'color' => 'var(--aureon-primary)'],
            ['label' => 'Active accounts', 'value' => $this->statistics['active'], 'icon' => 'ti-user-check', 'color' => 'var(--aureon-secondary)'],
            ['label' => 'Administrators', 'value' => $this->statistics['administrators'], 'icon' => 'ti-shield-check', 'color' => '#9a6d1f'],
            ['label' => 'Profiles pending', 'value' => $this->statistics['incomplete'], 'icon' => 'ti-user-question', 'color' => '#596274'],
        ];
    @endphp

    <section class="row" aria-label="User statistics">
        @foreach ($userStats as $stat)
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

    <section class="card aureon-panel mb-4" aria-labelledby="user-filters-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <h3 id="user-filters-title" class="card-title mb-0">Account directory</h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="clearFilters" title="Clear filters" aria-label="Clear user filters"><i class="ti ti-filter-off"></i></button>
                @can(\App\Support\CmsPermission::MANAGE_USERS)
                    <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-user-plus me-2"></i>Add user</button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-4 col-md-6">
                    <label for="user-search" class="form-label">Search</label>
                    <div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="user-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Name, username, email, phone, or ID"></div>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label for="user-type-filter" class="form-label">User type</label>
                    <select id="user-type-filter" class="form-select" wire:model.live="userTypeFilter">
                        <option value="">All types</option>
                        @foreach ($this->userTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="user-role-filter" class="form-label">Role</label>
                    <select id="user-role-filter" class="form-select" wire:model.live="roleFilter">
                        <option value="">All roles</option>
                        @foreach ($this->roles as $role)<option value="{{ $role->name }}">{{ str($role->name)->replace('-', ' ')->title() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="user-status-filter" class="form-label">Status</label>
                    <select id="user-status-filter" class="form-select" wire:model.live="statusFilter"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="user-per-page" class="form-label">Rows</label>
                    <select id="user-per-page" class="form-select" wire:model.live="perPage">@foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select>
                </div>
            </div>
        </div>
    </section>

    <section id="user-table" class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="user-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div><h3 id="user-table-title" class="card-title mb-1">User accounts</h3><p class="aureon-muted fs-12 mb-0">{{ number_format($this->users->total()) }} matching accounts</p></div>
            <div class="aureon-activity-loading position-static" wire:loading.delay aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Updating</span></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 aureon-user-table">
                <thead><tr><th scope="col">User</th><th scope="col">Contact</th><th scope="col">Access</th><th scope="col">Status</th><th scope="col">Last login</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if ($user->profilePhotoUrl())<img src="{{ $user->profilePhotoUrl() }}" alt="" class="aureon-avatar-img">@else<span class="aureon-user-avatar">{{ $user->initials }}</span>@endif
                                    <div><span class="d-block fw-semibold">{{ $user->display_name }}</span><small class="aureon-muted">{{ '@'.$user->name }}</small></div>
                                </div>
                            </td>
                            <td><span class="d-block">{{ $user->email }}</span><small class="aureon-muted">{{ $user->profile?->phone ?: 'No phone recorded' }}</small></td>
                            <td><span class="badge aureon-role-badge">{{ $user->user_type->label() }}</span><small class="aureon-muted d-block mt-1">{{ $user->roles->pluck('name')->map(fn ($name) => str($name)->replace('-', ' ')->title())->implode(', ') }}</small><small class="d-block mt-1 {{ $user->two_factor_enabled ? 'text-success' : 'aureon-muted' }}"><i class="ti {{ $user->two_factor_enabled ? 'ti-shield-check' : 'ti-shield-off' }} me-1"></i>2FA {{ $user->two_factor_enabled ? 'enabled' : 'disabled' }}</small></td>
                            <td><span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>@if (! $user->email_verified_at)<small class="text-warning d-block mt-1">Email unverified</small>@endif</td>
                            <td class="text-nowrap">@if ($user->last_login_at)<span class="d-block">{{ $user->last_login_at->format('d M Y') }}</span><small class="aureon-muted">{{ $user->last_login_at->format('H:i') }}</small>@else<span class="aureon-muted">Never</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openView({{ $user->id }})" title="View user" aria-label="View {{ $user->display_name }}"><i class="ti ti-eye"></i></button>
                                @can(\App\Support\CmsPermission::MANAGE_USERS)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $user->id }})" title="Edit user" aria-label="Edit {{ $user->display_name }}"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="toggleStatus({{ $user->id }})" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }} user" aria-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->display_name }}"><i class="ti {{ $user->is_active ? 'ti-user-pause' : 'ti-user-check' }}"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="confirmDelete({{ $user->id }})" title="Delete user" aria-label="Delete {{ $user->display_name }}"><i class="ti ti-trash"></i></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5"><span class="aureon-empty-state__icon"><i class="ti ti-users-off"></i></span><h4 class="fs-16 mb-1">No user accounts found</h4><p class="aureon-muted mb-0">Adjust the filters or create the first matching account.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->users->hasPages())<div class="card-footer bg-transparent border-top">{{ $this->users->links(data: ['scrollTo' => '#user-table']) }}</div>@endif
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedUserId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="user-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><div><h3 id="user-form-title" class="modal-title fs-18">{{ $editing ? 'Edit user account' : 'Create user account' }}</h3><p class="aureon-muted fs-12 mb-0">Account, profile, and access details</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close user form"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <section class="aureon-form-section">
                                    <h4>Account</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6"><label for="user-name" class="form-label">Username <span class="text-danger">*</span></label><input id="user-name" type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model.live.blur="form.name" autocomplete="off">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-6"><label for="user-email" class="form-label">Email <span class="text-danger">*</span></label><input id="user-email" type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model.live.blur="form.email" autocomplete="off">@error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-6"><label for="user-password" class="form-label">{{ $editing ? 'New password' : 'Password' }} @if (! $editing)<span class="text-danger">*</span>@endif</label><input id="user-password" type="password" class="form-control @error('form.password') is-invalid @enderror" wire:model="form.password" autocomplete="new-password">@error('form.password')<div class="invalid-feedback">{{ $message }}</div>@enderror@if ($editing)<small class="aureon-muted">Leave blank to retain the current password.</small>@endif</div>
                                        <div class="col-md-6"><label for="user-password-confirmation" class="form-label">Confirm password</label><input id="user-password-confirmation" type="password" class="form-control" wire:model="form.password_confirmation" autocomplete="new-password"></div>
                                    </div>
                                </section>
                                <section class="aureon-form-section">
                                    <h4>Personal profile</h4>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label for="user-first-name" class="form-label">First name <span class="text-danger">*</span></label><input id="user-first-name" type="text" class="form-control @error('form.firstName') is-invalid @enderror" wire:model.live.blur="form.firstName">@error('form.firstName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-middle-name" class="form-label">Middle name</label><input id="user-middle-name" type="text" class="form-control @error('form.middleName') is-invalid @enderror" wire:model.live.blur="form.middleName">@error('form.middleName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-last-name" class="form-label">Last name <span class="text-danger">*</span></label><input id="user-last-name" type="text" class="form-control @error('form.lastName') is-invalid @enderror" wire:model.live.blur="form.lastName">@error('form.lastName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-dob" class="form-label">Date of birth</label><input id="user-dob" type="date" class="form-control @error('form.dateOfBirth') is-invalid @enderror" wire:model.live.blur="form.dateOfBirth">@error('form.dateOfBirth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-phone" class="form-label">Phone</label><input id="user-phone" type="tel" class="form-control @error('form.phone') is-invalid @enderror" wire:model.live.blur="form.phone">@error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-job-title" class="form-label">Job title</label><input id="user-job-title" type="text" class="form-control @error('form.jobTitle') is-invalid @enderror" wire:model.live.blur="form.jobTitle">@error('form.jobTitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-4"><label for="user-identification-type" class="form-label">Identification type</label><select id="user-identification-type" class="form-select @error('form.identificationType') is-invalid @enderror" wire:model.live="form.identificationType"><option value="">Not recorded</option>@foreach ($this->identificationTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('form.identificationType')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-md-8"><label for="user-identification-number" class="form-label">ID or passport number</label><input id="user-identification-number" type="text" class="form-control @error('form.identificationNumber') is-invalid @enderror" wire:model.live.blur="form.identificationNumber">@error('form.identificationNumber')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        <div class="col-12"><label for="user-bio" class="form-label">Biography</label><textarea id="user-bio" rows="3" class="form-control @error('form.bio') is-invalid @enderror" wire:model.live.blur="form.bio"></textarea><div class="d-flex justify-content-between">@error('form.bio')<span class="text-danger fs-12">{{ $message }}</span>@else<span></span>@enderror<small class="aureon-muted">{{ strlen($form->bio) }}/2000</small></div></div>
                                    </div>
                                </section>
                            </div>
                            <div class="col-lg-4">
                                <section class="aureon-form-section">
                                    <h4>Profile photo</h4>
                                    <div class="aureon-profile-photo-editor">
                                        @if ($profilePhotoUpload && $profilePhotoUpload->isPreviewable() && ! $errors->has('profilePhotoUpload'))<img src="{{ $profilePhotoUpload->temporaryUrl() }}" alt="New profile photo preview">@elseif ($form->user?->profilePhotoUrl())<img src="{{ $form->user->profilePhotoUrl() }}" alt="Current profile photo">@else<span class="aureon-user-avatar aureon-user-avatar--large">{{ $form->user?->initials ?? 'U' }}</span>@endif
                                    </div>
                                    <label for="user-profile-photo" class="form-label mt-3">Choose image</label><input id="user-profile-photo" type="file" class="form-control @error('profilePhotoUpload') is-invalid @enderror" wire:model="profilePhotoUpload" accept="image/jpeg,image/png,image/webp">@error('profilePhotoUpload')<div class="invalid-feedback">{{ $message }}</div>@enderror<div wire:loading wire:target="profilePhotoUpload" class="aureon-muted fs-12 mt-2">Uploading preview...</div>
                                    @if ($form->user?->profilePhotoUrl())<div class="form-check mt-3"><input id="remove-user-photo" type="checkbox" class="form-check-input" wire:model="form.removeProfilePhoto"><label for="remove-user-photo" class="form-check-label">Remove current photo</label></div>@endif
                                </section>
                                <section class="aureon-form-section">
                                    <h4>Access</h4>
                                    <label for="user-type" class="form-label">User type <span class="text-danger">*</span></label><select id="user-type" class="form-select @error('form.userType') is-invalid @enderror" wire:model.live="form.userType">@foreach ($this->userTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select>@error('form.userType')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <p class="aureon-muted fs-12 mt-2">The user type adds its matching base role. Permissions remain role-based.</p>
                                    <div class="form-check form-switch mb-3"><input id="user-active" type="checkbox" class="form-check-input" role="switch" wire:model="form.isActive"><label for="user-active" class="form-check-label">Account active</label></div>
                                    <div class="form-check form-switch mb-1"><input id="user-two-factor-enabled" type="checkbox" class="form-check-input" role="switch" wire:model="form.twoFactorEnabled"><label for="user-two-factor-enabled" class="form-check-label">Two-factor authentication</label></div>
                                    <p class="aureon-muted fs-12 mb-3">Enabled by default for new accounts. The preference takes effect when the system-wide 2FA switch is active.</p>
                                    @if ($this->customRoles->isNotEmpty())
                                        <p class="form-label mb-2">Additional roles</p>
                                        <div class="aureon-check-list">@foreach ($this->customRoles as $role)<div class="form-check" wire:key="additional-role-{{ $role->id }}"><input id="additional-role-{{ $role->id }}" type="checkbox" class="form-check-input" value="{{ $role->name }}" wire:model="form.additionalRoles"><label for="additional-role-{{ $role->id }}" class="form-check-label">{{ str($role->name)->replace('-', ' ')->title() }}</label></div>@endforeach</div>
                                    @else<p class="aureon-muted fs-12 mb-0">No custom roles have been created.</p>@endif
                                </section>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,profilePhotoUpload"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create user' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close user form"></button>
    @endif

    @if ($dialog === 'view' && $this->selectedUser)
        @php($selected = $this->selectedUser)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="user-details-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
                <div class="modal-header"><div class="d-flex align-items-center gap-3">@if ($selected->profilePhotoUrl())<img src="{{ $selected->profilePhotoUrl() }}" alt="" class="aureon-avatar-img aureon-avatar-img--large">@else<span class="aureon-user-avatar aureon-user-avatar--large">{{ $selected->initials }}</span>@endif<div><h3 id="user-details-title" class="modal-title fs-18">{{ $selected->display_name }}</h3><p class="aureon-muted fs-12 mb-0">{{ '@'.$selected->name }}</p></div></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close user details"></button></div>
                <div class="modal-body"><dl class="row g-3 mb-0 aureon-activity-details">
                    <div class="col-md-6"><dt>Email</dt><dd>{{ $selected->email }}</dd></div><div class="col-md-6"><dt>Phone</dt><dd>{{ $selected->profile?->phone ?: 'Not recorded' }}</dd></div>
                    <div class="col-md-6"><dt>User type</dt><dd>{{ $selected->user_type->label() }}</dd></div><div class="col-md-6"><dt>Status</dt><dd>{{ $selected->is_active ? 'Active' : 'Inactive' }}</dd></div>
                    <div class="col-md-6"><dt>Two-factor authentication</dt><dd>{{ $selected->two_factor_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                    <div class="col-md-6"><dt>Job title</dt><dd>{{ $selected->profile?->job_title ?: 'Not recorded' }}</dd></div><div class="col-md-6"><dt>Date of birth</dt><dd>{{ $selected->profile?->date_of_birth?->format('d M Y') ?: 'Not recorded' }}</dd></div>
                    <div class="col-md-6"><dt>Identification</dt><dd>{{ $selected->profile?->identification_type?->label() ?: 'Not recorded' }}@if ($selected->profile?->identification_number)<span class="d-block">{{ $selected->profile->identification_number }}</span>@endif</dd></div><div class="col-md-6"><dt>Last login</dt><dd>{{ $selected->last_login_at?->format('d M Y, H:i') ?: 'Never' }}</dd></div>
                    <div class="col-12"><dt>Roles</dt><dd class="d-flex flex-wrap gap-2">@foreach ($selected->roles as $role)<span class="badge aureon-role-badge">{{ str($role->name)->replace('-', ' ')->title() }}</span>@endforeach</dd></div>
                    <div class="col-12"><dt>Biography</dt><dd>{{ $selected->profile?->bio ?: 'No biography recorded.' }}</dd></div>
                </dl></div>
                <div class="modal-footer">@can(\App\Support\CmsPermission::MANAGE_USERS)<button type="button" class="btn btn-outline-secondary" wire:click="openEdit({{ $selected->id }})"><i class="ti ti-edit me-2"></i>Edit</button>@endcan<button type="button" class="btn btn-primary" wire:click="closeDialog">Close</button></div>
            </div></div>
        </div><button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close user details"></button>
    @endif

    @if ($dialog === 'delete' && $this->selectedUser)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="delete-user-title" wire:keydown.escape.window="closeDialog"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><h3 id="delete-user-title" class="modal-title fs-18">Delete user account</h3><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close delete confirmation"></button></div><div class="modal-body"><p class="mb-2">Delete <strong>{{ $this->selectedUser->display_name }}</strong>?</p><p class="aureon-muted mb-0">This permanently removes the account, personal profile, role assignments, and profile photo. Audit history remains.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="button" class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"><i class="ti ti-trash me-2"></i>Delete account</button></div></div></div></div><button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close delete confirmation"></button>
    @endif
</div>
