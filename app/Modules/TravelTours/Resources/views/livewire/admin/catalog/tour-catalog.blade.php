<section id="travel-tour-catalog" aria-labelledby="travel-tour-catalog-title">
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="card aureon-panel">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-tour-catalog-title" class="card-title mb-1">Tours</h3>
                <p class="aureon-muted mb-0 fs-12">{{ number_format($tours->total()) }} matching {{ Str::plural('tour', $tours->total()) }}</p>
            </div>
            @can('create', App\Modules\TravelTours\Catalog\Models\Tour::class)
                <a href="{{ route('travel-tours.admin.catalog.tours.create') }}" class="btn btn-primary"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add tour</a>
            @endcan
        </div>

        <div class="travel-catalog-status-counts" aria-label="Tour publication counts">
            @foreach(App\Modules\TravelTours\Catalog\Enums\PublicationStatus::cases() as $state)
                <span><strong>{{ number_format($statusCounts[$state->value] ?? 0) }}</strong> {{ $state->label() }}</span>
            @endforeach
        </div>

        <div class="card-body border-bottom">
            <div class="travel-admin-filters">
                <div class="travel-admin-filter travel-admin-filter--search">
                    <label for="travel-tour-search" class="form-label">Search tours</label>
                    <div class="input-group"><span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span><input id="travel-tour-search" type="search" class="form-control" placeholder="Name or code" wire:model.live.debounce.350ms="search"></div>
                </div>
                <div class="travel-admin-filter"><label for="travel-tour-status" class="form-label">Status</label><select id="travel-tour-status" class="form-select" wire:model.live="statusFilter"><option value="">All statuses</option>@foreach(App\Modules\TravelTours\Catalog\Enums\PublicationStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
                <div class="travel-admin-filter"><label for="travel-tour-type" class="form-label">Type</label><select id="travel-tour-type" class="form-select" wire:model.live="typeFilter"><option value="">All types</option>@foreach(App\Modules\TravelTours\Catalog\Enums\TourType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
                <div class="travel-admin-filter"><label for="travel-tour-category" class="form-label">Category</label><select id="travel-tour-category" class="form-select" wire:model.live="categoryFilter"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                <div class="travel-admin-filter"><label for="travel-tour-destination" class="form-label">Destination</label><select id="travel-tour-destination" class="form-select" wire:model.live="destinationFilter"><option value="">All destinations</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select></div>
                <div class="travel-admin-filter travel-admin-filter--compact"><label for="travel-tour-page-size" class="form-label">Rows</label><select id="travel-tour-page-size" class="form-select" wire:model.live="perPage"><option value="12">12</option><option value="24">24</option><option value="48">48</option></select></div>
                <div class="travel-admin-filter travel-admin-filter--action"><button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table travel-admin-table mb-0">
                <thead><tr><th>Tour</th><th>Format</th><th>Discovery</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($tours as $tour)
                        @php($cover = $tour->getFirstMedia('tour_cover'))
                        <tr wire:key="travel-tour-{{ $tour->id }}">
                            <td><div class="d-flex align-items-center gap-3">@if($cover)<img class="travel-admin-thumb" src="{{ $cover->hasGeneratedConversion('thumb') ? $cover->getUrl('thumb') : $cover->getUrl() }}" alt="">@else<span class="travel-admin-thumb travel-admin-thumb--empty"><i class="ti ti-photo" aria-hidden="true"></i></span>@endif<div><strong class="d-block">{{ $tour->name }}</strong><small class="aureon-muted">{{ $tour->code }}</small></div></div></td>
                            <td><span class="d-block">{{ $tour->type->label() }}</span><small class="aureon-muted">{{ $tour->duration_days }} {{ Str::plural('day', $tour->duration_days) }} &middot; {{ $tour->difficulty->label() }}</small></td>
                            <td><span class="d-block">{{ $tour->categories->pluck('name')->join(', ') ?: 'No category' }}</span><small class="aureon-muted">{{ $tour->destinations->pluck('name')->join(' / ') ?: 'Route not assigned' }}</small></td>
                            <td><span class="travel-status {{ $tour->status->value === 'published' ? 'travel-status--active' : ($tour->status->value === 'review' ? 'travel-status--review' : 'travel-status--muted') }}">{{ $tour->status->label() }}</span></td>
                            <td><div class="travel-admin-row-actions justify-content-end">@can('update', $tour)<a class="btn btn-icon btn-sm btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.tours.edit', $tour) }}" aria-label="Edit {{ $tour->name }}" title="Edit tour"><i class="ti ti-pencil" aria-hidden="true"></i></a>@endcan @if($tour->status->value === 'published')<a class="btn btn-icon btn-sm btn-outline-secondary" href="{{ route('travel-tours.storefront.tours.show', $tour->slug) }}" target="_blank" rel="noopener noreferrer" aria-label="View {{ $tour->name }} on the public site" title="View public tour"><i class="ti ti-external-link" aria-hidden="true"></i></a>@endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="travel-admin-empty"><i class="ti ti-route" aria-hidden="true"></i><strong>No tours match these filters</strong><span>Clear the filters or create a tour draft.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tours->hasPages())<div class="card-footer">{{ $tours->links() }}</div>@endif
    </div>
</section>
