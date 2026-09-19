<div id="system-activity-explorer">
    @php
        $statCards = [
            ['label' => 'Matching activities', 'value' => $this->statistics['total'], 'icon' => 'ti-activity', 'color' => 'var(--aureon-primary)'],
            ['label' => 'Recorded today', 'value' => $this->statistics['today'], 'icon' => 'ti-calendar-event', 'color' => 'var(--aureon-secondary)'],
            ['label' => 'Active actors', 'value' => $this->statistics['actors'], 'icon' => 'ti-users', 'color' => '#9a6d1f'],
            ['label' => 'Needs attention', 'value' => $this->statistics['attention'], 'icon' => 'ti-alert-triangle', 'color' => '#b42318'],
        ];
    @endphp

    <section class="row" aria-label="Activity statistics">
        @foreach ($statCards as $stat)
            <div class="col-xl-3 col-sm-6 d-flex">
                <article class="card aureon-stat mb-4" style="--stat-color: {{ $stat['color'] }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="aureon-stat__icon"><i class="ti {{ $stat['icon'] }}"></i></span>
                        <div>
                            <h3>{{ number_format($stat['value']) }}</h3>
                            <p>{{ $stat['label'] }}</p>
                        </div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="card aureon-panel mb-4" aria-labelledby="activity-filters-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <h3 id="activity-filters-title" class="card-title mb-0">Filters</h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="refreshActivities" title="Refresh activities" aria-label="Refresh activities">
                    <i class="ti ti-refresh"></i>
                </button>
                <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="clearFilters" title="Clear filters" aria-label="Clear filters">
                    <i class="ti ti-filter-off"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-4 col-md-6">
                    <label for="activity-search" class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input id="activity-search" type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Event, description, actor, route, or IP">
                    </div>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label for="activity-type" class="form-label">Event</label>
                    <select id="activity-type" class="form-select" wire:model.live="activityType">
                        <option value="">All events</option>
                        @foreach ($this->activityTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="activity-severity" class="form-label">Severity</label>
                    <select id="activity-severity" class="form-select" wire:model.live="severity">
                        <option value="">All severities</option>
                        @foreach ($this->severities as $severityOption)
                            <option value="{{ $severityOption->value }}">{{ $severityOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="activity-actor" class="form-label">Actor</label>
                    <select id="activity-actor" class="form-select" wire:model.live="actorId">
                        <option value="">All actors</option>
                        @foreach ($this->activeActors as $actor)
                            <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label for="activity-per-page" class="form-label">Rows</label>
                    <select id="activity-per-page" class="form-select" wire:model.live="perPage">
                        @foreach ([10, 25, 50, 100] as $pageSize)
                            <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="activity-date-from" class="form-label">From</label>
                    <input id="activity-date-from" type="date" class="form-control" wire:model.live="dateFrom">
                </div>
                <div class="col-md-3">
                    <label for="activity-date-to" class="form-label">To</label>
                    <input id="activity-date-to" type="date" class="form-control" wire:model.live="dateTo">
                </div>
            </div>
        </div>
    </section>

    <section id="activity-table" class="card aureon-panel mb-4" aria-labelledby="activity-table-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div>
                <h3 id="activity-table-title" class="card-title mb-1">Activity records</h3>
                <p class="aureon-muted fs-12 mb-0">{{ number_format($this->activities->total()) }} matching records</p>
            </div>
            <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="toggleSortDirection" title="Reverse chronological order" aria-label="Reverse chronological order">
                <i class="ti {{ $sortDirection === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending' }}"></i>
            </button>
        </div>

        <div class="position-relative">
            <div class="aureon-activity-loading" wire:loading.delay aria-live="polite">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Updating</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 aureon-activity-table">
                    <thead>
                        <tr>
                            <th scope="col">Occurred</th>
                            <th scope="col">Event</th>
                            <th scope="col">Description</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Source</th>
                            <th scope="col">Subject</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->activities as $activity)
                            @php
                                $severityClass = match ($activity->severity) {
                                    \App\Enums\SystemActivitySeverity::Info => 'aureon-severity-badge--info',
                                    \App\Enums\SystemActivitySeverity::Notice => 'aureon-severity-badge--notice',
                                    \App\Enums\SystemActivitySeverity::Warning => 'aureon-severity-badge--warning',
                                    \App\Enums\SystemActivitySeverity::Error,
                                    \App\Enums\SystemActivitySeverity::Critical => 'aureon-severity-badge--danger',
                                };
                            @endphp
                            <tr wire:key="activity-{{ $activity->id }}">
                                <td class="text-nowrap">
                                    <span class="d-block fw-semibold">{{ $activity->created_at->format('d M Y') }}</span>
                                    <small class="aureon-muted">{{ $activity->created_at->format('H:i:s') }}</small>
                                </td>
                                <td>
                                    <code class="aureon-event-name">{{ $activity->activity_type }}</code>
                                    <span class="aureon-severity-badge {{ $severityClass }} d-table mt-2">{{ $activity->severity->label() }}</span>
                                </td>
                                <td class="aureon-activity-description">{{ \Illuminate\Support\Str::limit($activity->description, 120) }}</td>
                                <td>
                                    @if ($activity->actor)
                                        <span class="d-block fw-semibold">{{ $activity->actor->name }}</span>
                                        <small class="aureon-muted">{{ $activity->actor->email }}</small>
                                    @else
                                        <span class="badge text-bg-light">System</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-capitalize">{{ $activity->source }}</span>
                                    @if ($activity->ip_address)
                                        <small class="aureon-muted d-block">{{ $activity->ip_address }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($activity->subject_type && $activity->subject_id)
                                        <span class="d-block">{{ class_basename($activity->subject_type) }}</span>
                                        <small class="aureon-muted">#{{ $activity->subject_id }}</small>
                                    @else
                                        <span class="aureon-muted">None</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openDetails({{ $activity->id }})" title="View activity" aria-label="View {{ $activity->activity_type }} activity">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <span class="aureon-empty-state__icon"><i class="ti ti-history-off"></i></span>
                                    <h4 class="fs-16 mb-1">No activity records</h4>
                                    <p class="aureon-muted mb-0">Adjust the current filters or record the first module event.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->activities->hasPages())
            <div class="card-footer bg-transparent border-top">
                {{ $this->activities->links(data: ['scrollTo' => '#activity-table']) }}
            </div>
        @endif
    </section>

    @if ($showDetails && $this->selectedActivity)
        @php($selected = $this->selectedActivity)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="activity-details-title" wire:keydown.escape.window="closeDetails">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 id="activity-details-title" class="modal-title fs-18">Activity details</h3>
                            <code class="aureon-event-name">{{ $selected->activity_type }}</code>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDetails" aria-label="Close activity details"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row g-3 mb-0 aureon-activity-details">
                            <div class="col-md-6">
                                <dt>Occurred</dt>
                                <dd>{{ $selected->created_at->format('d M Y, H:i:s T') }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Severity</dt>
                                <dd>{{ $selected->severity->label() }}</dd>
                            </div>
                            <div class="col-12">
                                <dt>Description</dt>
                                <dd>{{ $selected->description }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Actor</dt>
                                <dd>{{ $selected->actor?->name ?? 'System process' }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Source</dt>
                                <dd class="text-capitalize">{{ $selected->source }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Subject</dt>
                                <dd>
                                    @if ($selected->subject_type && $selected->subject_id)
                                        {{ class_basename($selected->subject_type) }} #{{ $selected->subject_id }}
                                    @else
                                        None
                                    @endif
                                </dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Batch UUID</dt>
                                <dd class="text-break">{{ $selected->batch_uuid ?? 'None' }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>Request</dt>
                                <dd>{{ $selected->request_method ?? 'N/A' }} {{ $selected->route_name ?? '' }}</dd>
                            </div>
                            <div class="col-md-6">
                                <dt>IP address</dt>
                                <dd>{{ $selected->ip_address ?? 'N/A' }}</dd>
                            </div>
                            @if ($selected->request_url)
                                <div class="col-12">
                                    <dt>URL</dt>
                                    <dd class="text-break">{{ $selected->request_url }}</dd>
                                </div>
                            @endif
                            @if ($selected->user_agent)
                                <div class="col-12">
                                    <dt>User agent</dt>
                                    <dd class="text-break">{{ $selected->user_agent }}</dd>
                                </div>
                            @endif
                            @if (filled($selected->properties))
                                <div class="col-12">
                                    <dt>Context</dt>
                                    <dd>
                                        <pre class="aureon-activity-context mb-0"><code>{{ json_encode($selected->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeDetails">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="modal-backdrop fade show border-0 w-100" wire:click="closeDetails" aria-label="Close activity details"></button>
    @endif
</div>
