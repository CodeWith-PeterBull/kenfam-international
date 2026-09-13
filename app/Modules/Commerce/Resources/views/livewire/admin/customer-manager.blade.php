<div id="commerce-customer-manager">
    @php($stats = [
        ['label' => 'Active customers', 'value' => $this->statistics['active'], 'icon' => 'ti-user-check', 'color' => 'var(--aureon-primary)'],
        ['label' => 'Archived', 'value' => $this->statistics['archived'], 'icon' => 'ti-user-off', 'color' => '#6c757d'],
        ['label' => 'Linked accounts', 'value' => $this->statistics['linked'], 'icon' => 'ti-link', 'color' => '#0f766e'],
        ['label' => 'Historical orders', 'value' => $this->statistics['orders'], 'icon' => 'ti-receipt', 'color' => 'var(--aureon-accent)'],
    ])

    <section class="row" aria-label="Customer statistics">
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

    <section class="card aureon-panel mb-4" aria-labelledby="customer-filters-title">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label id="customer-filters-title" for="customer-search" class="form-label">Search customers</label>
                    <div class="input-group"><span class="input-group-text"><i class="ti ti-search"></i></span><input id="customer-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Name, company, email, or phone"></div>
                </div>
                <div class="col-lg-3 col-md-5">
                    <label for="customer-state" class="form-label">Record state</label>
                    <select id="customer-state" class="form-select" wire:model.live="state"><option value="active">Active</option><option value="archived">Archived</option><option value="all">All records</option></select>
                </div>
                <div class="col-lg-3 col-md-7 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-icon" wire:click="clearFilters" title="Clear filters" aria-label="Clear customer filters"><i class="ti ti-filter-off"></i></button>
                    <button type="button" class="btn btn-primary flex-grow-1" wire:click="openCreate"><i class="ti ti-user-plus me-2"></i>Add customer</button>
                </div>
            </div>
        </div>
    </section>

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="customer-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div><h3 id="customer-table-title" class="card-title mb-1">Customer directory</h3><p class="aureon-muted fs-12 mb-0">{{ number_format($this->customers->total()) }} matching records</p></div>
            <div class="d-flex align-items-center gap-2"><span class="aureon-activity-loading position-static" wire:loading.delay aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Updating</span></span><select class="form-select form-select-sm aureon-per-page" wire:model.live="perPage" aria-label="Customers per page">@foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}">{{ $size }} / page</option>@endforeach</select></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 aureon-commerce-table">
                <thead><tr><th scope="col">Customer</th><th scope="col">Contact</th><th scope="col">Address</th><th scope="col">Account</th><th scope="col">Orders</th><th scope="col">State</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($this->customers as $customer)
                        <tr wire:key="customer-{{ $customer->id }}">
                            <td><span class="d-block fw-semibold">{{ $customer->display_name }}</span><small class="aureon-muted">{{ $customer->company ?: 'Individual customer' }}</small></td>
                            <td><span class="d-block">{{ $customer->email ?: 'No email' }}</span><small class="aureon-muted">{{ $customer->phone ?: 'No phone' }}</small></td>
                            <td><span class="d-block">{{ $customer->city ?: 'Not provided' }}</span><small class="aureon-muted">{{ $customer->country_code }}</small></td>
                            <td>@if ($customer->user)<span class="badge aureon-commerce-badge aureon-commerce-badge--published">Linked</span><small class="d-block aureon-muted mt-1">{{ $customer->user->email }}</small>@else<span class="badge aureon-commerce-badge aureon-commerce-badge--neutral">Guest profile</span>@endif</td>
                            <td><span class="fw-semibold">{{ number_format($customer->orders_count) }}</span></td>
                            <td><span class="badge aureon-commerce-badge {{ $customer->trashed() ? 'aureon-commerce-badge--archived' : 'aureon-commerce-badge--published' }}">{{ $customer->trashed() ? 'Archived' : 'Active' }}</span></td>
                            <td class="text-end text-nowrap">
                                @if ($customer->trashed())
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="restore({{ $customer->id }})" wire:confirm="Restore this customer record?" title="Restore customer" aria-label="Restore {{ $customer->display_name }}"><i class="ti ti-restore"></i></button>
                                @else
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $customer->id }})" title="Edit customer" aria-label="Edit {{ $customer->display_name }}"><i class="ti ti-edit"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="archive({{ $customer->id }})" wire:confirm="Archive this customer? Historical orders will remain available." title="Archive customer" aria-label="Archive {{ $customer->display_name }}"><i class="ti ti-archive"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5"><span class="aureon-empty-state__icon"><i class="ti ti-users-off"></i></span><h4 class="fs-16 mb-1">No customers found</h4><p class="aureon-muted mb-0">Adjust the filters or create the first reusable customer.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->customers->hasPages())<div class="card-footer">{{ $this->customers->onEachSide(1)->links() }}</div>@endif
    </section>

    @if ($dialog === 'form')
        @php($editing = $selectedCustomerId !== null)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="customer-form-title" wire:keydown.escape.window="closeDialog">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="save">
                    <div class="modal-header"><div><h3 id="customer-form-title" class="modal-title fs-18">{{ $editing ? 'Edit customer' : 'Create customer' }}</h3><p class="aureon-muted fs-12 mb-0">Reusable identity, optional user link, contact details, and default address</p></div><button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close customer form"></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        <section class="aureon-form-section">
                            <h4>Identity and account</h4>
                            <div class="row g-3">
                                <div class="col-md-6"><label for="customer-first-name" class="form-label">First name <span class="text-danger">*</span></label><input id="customer-first-name" type="text" class="form-control @error('form.firstName') is-invalid @enderror" wire:model="form.firstName" autocomplete="given-name">@error('form.firstName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label for="customer-last-name" class="form-label">Last name <span class="text-danger">*</span></label><input id="customer-last-name" type="text" class="form-control @error('form.lastName') is-invalid @enderror" wire:model="form.lastName" autocomplete="family-name">@error('form.lastName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label for="customer-company" class="form-label">Company</label><input id="customer-company" type="text" class="form-control @error('form.company') is-invalid @enderror" wire:model="form.company" autocomplete="organization">@error('form.company')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-6"><label for="customer-tax-id" class="form-label">Tax identifier</label><input id="customer-tax-id" type="text" class="form-control @error('form.taxIdentifier') is-invalid @enderror" wire:model="form.taxIdentifier">@error('form.taxIdentifier')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="customer-user" class="form-label">Linked user account</label><select id="customer-user" class="form-select @error('form.userId') is-invalid @enderror" wire:model="form.userId"><option value="">No linked account</option>@foreach ($this->availableAccounts as $account)<option value="{{ $account->id }}">{{ $account->display_name }} ({{ $account->email }})</option>@endforeach</select>@error('form.userId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>
                        </section>
                        <section class="aureon-form-section">
                            <h4>Contact</h4>
                            <div class="row g-3"><div class="col-md-6"><label for="customer-email" class="form-label">Email</label><input id="customer-email" type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email" autocomplete="email">@error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label for="customer-phone" class="form-label">Phone</label><input id="customer-phone" type="tel" class="form-control @error('form.phone') is-invalid @enderror" wire:model="form.phone" autocomplete="tel">@error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                        </section>
                        <section class="aureon-form-section">
                            <h4>Default address</h4>
                            <div class="row g-3">
                                <div class="col-12"><label for="customer-address-1" class="form-label">Address line 1</label><input id="customer-address-1" type="text" class="form-control @error('form.addressLine1') is-invalid @enderror" wire:model="form.addressLine1" autocomplete="address-line1">@error('form.addressLine1')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><label for="customer-address-2" class="form-label">Address line 2</label><input id="customer-address-2" type="text" class="form-control @error('form.addressLine2') is-invalid @enderror" wire:model="form.addressLine2" autocomplete="address-line2">@error('form.addressLine2')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="customer-city" class="form-label">City</label><input id="customer-city" type="text" class="form-control @error('form.city') is-invalid @enderror" wire:model="form.city" autocomplete="address-level2">@error('form.city')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-4"><label for="customer-region" class="form-label">Region or county</label><input id="customer-region" type="text" class="form-control @error('form.region') is-invalid @enderror" wire:model="form.region" autocomplete="address-level1">@error('form.region')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-2"><label for="customer-postal" class="form-label">Postal code</label><input id="customer-postal" type="text" class="form-control @error('form.postalCode') is-invalid @enderror" wire:model="form.postalCode" autocomplete="postal-code">@error('form.postalCode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-md-2"><label for="customer-country" class="form-label">Country</label><input id="customer-country" type="text" maxlength="2" class="form-control text-uppercase @error('form.countryCode') is-invalid @enderror" wire:model="form.countryCode" autocomplete="country">@error('form.countryCode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeDialog">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="ti ti-device-floppy me-2"></i><span wire:loading.remove wire:target="save">{{ $editing ? 'Save changes' : 'Create customer' }}</span><span wire:loading wire:target="save">Saving...</span></button></div>
                </form>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDialog" aria-label="Close customer form"></button>
    @endif
</div>
