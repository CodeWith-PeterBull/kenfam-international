<div id="property-booking-unit-readiness-manager" class="pb-workspace">
    @php
        $stats = $this->statistics;
    @endphp
    @include('property-booking::livewire.admin.partials.stat-grid', ['cards' => [
        ['label' => 'Ready to allocate', 'value' => $stats['ready'], 'icon' => 'ti-circle-check', 'color' => '#2f7d64'],
        ['label' => 'Dirty', 'value' => $stats['dirty'], 'icon' => 'ti-sparkles', 'color' => '#9b6a23'],
        ['label' => 'Cleaning', 'value' => $stats['cleaning'], 'icon' => 'ti-bucket', 'color' => '#2c6e93'],
        ['label' => 'Unavailable', 'value' => $stats['unavailable'], 'icon' => 'ti-tool', 'color' => '#a64242'],
    ]])

    @if(session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>@endif
    @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card aureon-panel aureon-table-panel" aria-labelledby="readiness-board-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap"><div><h3 id="readiness-board-title" class="card-title mb-1">Housekeeping board</h3><p class="aureon-muted fs-12 mb-0">Active accommodation units ordered by operational attention</p></div><span class="pb-badge pb-badge--neutral">{{ number_format($this->units->total()) }} units</span></div>
        <div class="pb-filterbar pb-filterbar--readiness">
            <div class="pb-filterbar__search"><label for="readiness-search" class="visually-hidden">Search units</label><i class="ti ti-search"></i><input id="readiness-search" type="search" class="form-control" placeholder="Unit code, name, or floor" wire:model.live.debounce.300ms="search"></div>
            <div><label for="readiness-property-filter" class="visually-hidden">Property</label><select id="readiness-property-filter" class="form-select" wire:model.live="propertyFilter"><option value="">All properties</option>@foreach($this->propertyOptions as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select></div>
            <div><label for="readiness-status-filter" class="visually-hidden">Readiness</label><select id="readiness-status-filter" class="form-select" wire:model.live="statusFilter"><option value="">All readiness states</option>@foreach($this->statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
            <div><label for="readiness-occupancy-filter" class="visually-hidden">Occupancy</label><select id="readiness-occupancy-filter" class="form-select" wire:model.live="occupancyFilter"><option value="">All occupancy</option><option value="occupied">In house</option><option value="reserved">Reserved</option><option value="vacant">Vacant</option></select></div>
            <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2"></i>Clear</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pb-table pb-table--readiness">
                <thead><tr><th>Unit</th><th>Property</th><th>Readiness</th><th>Occupancy context</th><th>Last ready</th><th class="text-end">Next action</th></tr></thead>
                <tbody>
                    @forelse($this->units as $unit)
                        @php
                            $assignment = $unit->assignments->first();
                            $booking = $assignment?->booking;
                            $statusClass = match($unit->operational_status) {
                                \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Ready => 'pb-badge--success',
                                \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Maintenance, \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::OutOfService => 'pb-badge--danger',
                                \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Cleaning => 'pb-badge--info',
                                default => 'pb-badge--warning',
                            };
                            $occupied = $booking?->stay_status === \App\Modules\PropertyBooking\Bookings\Enums\StayStatus::CheckedIn;
                        @endphp
                        <tr wire:key="readiness-unit-{{ $unit->id }}">
                            <td><strong class="d-block">{{ $unit->display_name ?: $unit->code }}</strong><code class="pb-code">{{ $unit->code }}</code>@if($unit->floor_label)<small class="d-block aureon-muted mt-1">{{ $unit->floor_label }}</small>@endif</td>
                            <td><span class="d-block">{{ $unit->property->name }}</span><small class="aureon-muted">{{ $unit->unitType->name }}</small></td>
                            <td><span class="pb-badge {{ $statusClass }}">{{ $unit->operational_status->label() }}</span></td>
                            <td>@if($booking)<strong class="d-block">{{ $occupied ? 'In house' : 'Reserved' }} &middot; {{ $booking->booking_number }}</strong><small class="aureon-muted">{{ trim($booking->guest_first_name.' '.$booking->guest_last_name) }} &middot; until {{ $booking->ends_at->timezone($booking->property_timezone ?? config('app.timezone'))->format('d M H:i') }}</small>@else<span class="pb-badge pb-badge--neutral">Vacant</span>@endif</td>
                            <td><span class="d-block">{{ $unit->last_ready_at?->diffForHumans() ?: 'Not recorded' }}</span><small class="aureon-muted">{{ $unit->last_ready_at?->format('d M Y H:i') }}</small></td>
                            <td class="text-end"><div class="pb-readiness-actions">@if($occupied)<span class="pb-badge pb-badge--neutral"><i class="ti ti-lock me-1"></i>Occupied</span>@else @foreach($unit->operational_status->allowedTransitions() as $target)<button type="button" class="btn btn-sm {{ $target === \App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus::Ready ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="changeReadiness({{ $unit->id }}, '{{ $target->value }}')" wire:loading.attr="disabled" wire:target="changeReadiness({{ $unit->id }}, '{{ $target->value }}')">{{ $target->label() }}</button>@endforeach @endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="pb-empty"><i class="ti ti-brush-off"></i><strong>No units match this readiness view</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center justify-content-between gap-3 flex-wrap"><div class="d-flex align-items-center gap-2"><label for="readiness-per-page" class="aureon-muted fs-11">Rows</label><select id="readiness-per-page" class="form-select form-select-sm pb-page-size" wire:model.live="perPage"><option>10</option><option>15</option><option>25</option><option>50</option></select></div>@if($this->units->hasPages()){{ $this->units->links() }}@endif</div>
    </section>
</div>
