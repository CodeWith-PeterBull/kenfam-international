@use('App\Modules\TravelTours\Inquiries\Enums\InquiryStatus')
@use('App\Modules\TravelTours\Inquiries\Enums\InquiryType')
@use('App\Modules\TravelTours\Inquiries\Services\TourInquiryService')
@use('App\Modules\TravelTours\Support\MoneyFormatter')
@php
    $user = auth()->user();
    $inquiries = $this->inquiries;
    $selected = $this->selectedInquiry;
    $statusClass = static fn (InquiryStatus $status): string => match ($status) {
        InquiryStatus::New, InquiryStatus::AwaitingCustomer => 'travel-status--review',
        InquiryStatus::Assigned, InquiryStatus::InProgress, InquiryStatus::Converted => 'travel-status--active',
        default => 'travel-status--muted',
    };
    $party = static fn ($inquiry): string => collect([[$inquiry->adult_count, 'adult'], [$inquiry->child_count, 'child'], [$inquiry->infant_count, 'infant']])
        ->filter(static fn (array $pair): bool => $pair[0] > 0)
        ->map(static fn (array $pair): string => $pair[0].' '.Str::plural($pair[1], $pair[0]))
        ->join(', ') ?: 'Party size not given';
    $request = static fn ($inquiry): string => $inquiry->tour?->name ?: (is_array($inquiry->requested_destinations) && $inquiry->requested_destinations !== [] ? implode(', ', $inquiry->requested_destinations) : 'General enquiry');
@endphp

<div id="travel-inquiry-manager">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('management')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <section class="card aureon-panel aureon-table-panel mb-4" aria-labelledby="travel-inquiry-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h3 id="travel-inquiry-title" class="card-title mb-1">Inquiries</h3>
                <p class="aureon-muted mb-0">{{ number_format($inquiries->total()) }} {{ Str::plural('conversation', $inquiries->total()) }}, newest first</p>
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="travel-admin-filters">
                <div class="travel-admin-filter travel-admin-filter--search">
                    <label for="travel-inquiry-search" class="form-label">Search inquiries</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
                        <input id="travel-inquiry-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Reference, name, email, or phone">
                    </div>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-inquiry-status-filter" class="form-label">Status</label>
                    <select id="travel-inquiry-status-filter" class="form-select" wire:model.live="statusFilter">
                        <option value="">All statuses</option>
                        @foreach (InquiryStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-inquiry-type-filter" class="form-label">Type</label>
                    <select id="travel-inquiry-type-filter" class="form-select" wire:model.live="typeFilter">
                        <option value="">All types</option>
                        @foreach (InquiryType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-inquiry-owner-filter" class="form-label">Owner</label>
                    <select id="travel-inquiry-owner-filter" class="form-select" wire:model.live="ownerFilter">
                        <option value="">Anyone</option>
                        <option value="mine">Assigned to me</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>
                <div class="travel-admin-filter">
                    <label for="travel-inquiry-due-filter" class="form-label">Follow-up</label>
                    <select id="travel-inquiry-due-filter" class="form-select" wire:model.live="followUpFilter">
                        <option value="">Any time</option>
                        <option value="overdue">Overdue</option>
                        <option value="today">Due today</option>
                        <option value="week">Due this week</option>
                        <option value="unscheduled">Not scheduled</option>
                    </select>
                </div>
                <div class="travel-admin-filter travel-admin-filter--action">
                    <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters"><i class="ti ti-filter-off me-2" aria-hidden="true"></i>Clear</button>
                </div>
            </div>
        </div>
        <div class="travel-catalog-status-counts" aria-label="Inquiry queue">
            <span><strong>{{ number_format($this->statistics['new']) }}</strong>new</span>
            <span><strong>{{ number_format($this->statistics['open']) }}</strong>open</span>
            <span><strong>{{ number_format($this->statistics['overdue']) }}</strong>overdue follow-ups</span>
            <span><strong>{{ number_format($this->statistics['awaiting']) }}</strong>awaiting customer</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 travel-admin-table">
                <thead>
                    <tr>
                        <th scope="col" class="travel-admin-index">#</th>
                        <th scope="col">Inquiry</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Request</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Follow-up</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inquiries as $inquiry)
                        @php
                            $overdue = $inquiry->follow_up_at !== null && $inquiry->follow_up_at->isPast() && ! $inquiry->status->isTerminal();
                            $canUpdate = $user->can('update', $inquiry);
                        @endphp
                        <tr wire:key="travel-inquiry-{{ $inquiry->id }}">
                            <td class="travel-admin-index">{{ $inquiries->firstItem() + $loop->index }}</td>
                            <td>
                                <strong class="d-block">{{ $inquiry->reference }}</strong>
                                <small class="aureon-muted">{{ $inquiry->inquiry_type->label() }} &middot; {{ $inquiry->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <span class="d-block text-break">{{ $inquiry->contact_name }}</span>
                                <small class="aureon-muted text-break">{{ $inquiry->contact_email ?: $inquiry->contact_phone }}@if ($inquiry->whatsapp_preferred) &middot; WhatsApp @endif</small>
                            </td>
                            <td>
                                <span class="d-block text-break">{{ $request($inquiry) }}</span>
                                <small class="aureon-muted">{{ $inquiry->preferred_start_date?->format('d M Y') ?: 'Flexible dates' }} &middot; {{ $party($inquiry) }}</small>
                            </td>
                            <td>{{ $inquiry->assignee?->name ?: 'Unassigned' }}</td>
                            <td>
                                @if ($inquiry->follow_up_at)
                                    <span class="d-block {{ $overdue ? 'text-danger fw-semibold' : '' }}">{{ $inquiry->follow_up_at->format('d M Y, H:i') }}</span>
                                    <small class="aureon-muted">{{ $overdue ? 'Overdue' : $inquiry->follow_up_at->diffForHumans() }}</small>
                                @else
                                    <span class="aureon-muted">Not scheduled</span>
                                @endif
                            </td>
                            <td><span class="travel-status {{ $statusClass($inquiry->status) }}">{{ $inquiry->status->label() }}</span></td>
                            <td class="text-end">
                                <div class="travel-admin-row-actions justify-content-end">
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openDetails({{ $inquiry->id }})" title="View inquiry" aria-label="View {{ $inquiry->reference }}">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </button>
                                    @if ($canUpdate && $inquiry->assigned_to === null && ! $inquiry->status->isTerminal())
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="assignToMe({{ $inquiry->id }})" wire:loading.attr="disabled" wire:target="assignToMe" title="Assign to me" aria-label="Assign {{ $inquiry->reference }} to me">
                                            <i class="ti ti-user-plus" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="travel-admin-empty">
                                    <i class="ti ti-messages" aria-hidden="true"></i>
                                    <strong>No inquiries match the current filters</strong>
                                    <span>New enquiries from the storefront land here.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($inquiries->hasPages())
            <div class="card-footer">{{ $inquiries->links() }}</div>
        @endif
    </section>

    @if ($dialog === 'details' && $selected)
        @php($canManage = $user->can('update', $selected))
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            aria-labelledby="travel-inquiry-detail-title" wire:keydown.escape.window="closeDialog"
            x-data x-init="$nextTick(() => $el.querySelector('.btn-close')?.focus())">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h3 id="travel-inquiry-detail-title" class="modal-title fs-18">{{ $selected->reference }}</h3>
                            <p class="aureon-muted fs-12 mb-0">
                                <span class="travel-status {{ $statusClass($selected->status) }}">{{ $selected->status->label() }}</span>
                                {{ $selected->inquiry_type->label() }} &middot; received {{ $selected->created_at->format('d M Y, H:i') }} via {{ $selected->source }}
                            </p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeDialog" aria-label="Close inquiry details"></button>
                    </div>
                    <div class="modal-body">
                        @error('management')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        <div class="travel-detail-grid">
                            <section aria-labelledby="travel-inquiry-detail-contact">
                                <h4 id="travel-inquiry-detail-contact">Contact</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Name</dt><dd>{{ $selected->contact_name }}</dd></div>
                                    <div><dt>Email</dt><dd>@if ($selected->contact_email)<a href="mailto:{{ $selected->contact_email }}">{{ $selected->contact_email }}</a>@else Not given @endif</dd></div>
                                    <div><dt>Phone</dt><dd>@if ($selected->contact_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $selected->contact_phone) }}">{{ $selected->contact_phone }}</a>@else Not given @endif</dd></div>
                                    <div><dt>Preferred channel</dt><dd>{{ $selected->whatsapp_preferred ? 'WhatsApp' : 'Email or phone' }}</dd></div>
                                    <div><dt>Customer profile</dt><dd>{{ $selected->customer?->full_name ?: 'Not linked' }}</dd></div>
                                    <div><dt>Consent</dt><dd>{{ $selected->consent_recorded_at ? $selected->consent_recorded_at->format('d M Y, H:i').' · '.($selected->consent_purpose ?: 'purpose not recorded') : 'Not recorded' }}</dd></div>
                                </dl>
                            </section>
                            <section aria-labelledby="travel-inquiry-detail-request">
                                <h4 id="travel-inquiry-detail-request">Request</h4>
                                <dl class="travel-detail-list">
                                    <div><dt>Tour</dt><dd>@if ($selected->tour)<a href="{{ route('travel-tours.admin.catalog.tours.edit', $selected->tour) }}">{{ $selected->tour->name }}</a>@else General enquiry @endif</dd></div>
                                    <div><dt>Departure</dt><dd>{{ $selected->departure?->code ?: 'Not chosen' }}</dd></div>
                                    <div><dt>Destinations</dt><dd>{{ is_array($selected->requested_destinations) && $selected->requested_destinations !== [] ? implode(', ', $selected->requested_destinations) : 'Not specified' }}</dd></div>
                                    <div><dt>Travel dates</dt><dd>{{ $selected->preferred_start_date?->format('d M Y') ?: 'Flexible' }}{{ $selected->preferred_end_date ? ' – '.$selected->preferred_end_date->format('d M Y') : '' }}</dd></div>
                                    <div><dt>Party</dt><dd>{{ $party($selected) }}</dd></div>
                                    <div><dt>Budget</dt><dd>{{ $selected->budget_minor !== null && $selected->budget_currency ? MoneyFormatter::format($selected->budget_minor, $selected->budget_currency) : 'Not stated' }}</dd></div>
                                    <div><dt>Converted booking</dt><dd>{{ $selected->convertedBooking?->booking_number ?: 'Not yet' }}</dd></div>
                                </dl>
                            </section>
                            <section class="travel-detail-grid__wide" aria-labelledby="travel-inquiry-detail-message">
                                <h4 id="travel-inquiry-detail-message">Message</h4>
                                <p class="mb-0 travel-inquiry-message">{{ $selected->message }}</p>
                            </section>

                            @if ($canManage)
                                <section class="travel-detail-grid__wide" aria-labelledby="travel-inquiry-detail-followup">
                                    <h4 id="travel-inquiry-detail-followup">Ownership and follow-up</h4>
                                    <form wire:submit="saveFollowUp" class="row g-3 align-items-end" novalidate>
                                        <div class="col-md-4">
                                            <label for="travel-inquiry-assignee" class="form-label">Owner</label>
                                            <select id="travel-inquiry-assignee" class="form-select @error('assigneeId') is-invalid @enderror" wire:model="assigneeId"
                                                @error('assigneeId') aria-invalid="true" aria-describedby="travel-inquiry-assignee-error" @enderror>
                                                <option value="">Unassigned</option>
                                                @foreach ($this->operators as $operator)
                                                    <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('assigneeId')<div id="travel-inquiry-assignee-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="travel-inquiry-status" class="form-label">Status</label>
                                            <select id="travel-inquiry-status" class="form-select @error('status') is-invalid @enderror" wire:model="status"
                                                @error('status') aria-invalid="true" aria-describedby="travel-inquiry-status-error" @enderror>
                                                @foreach ($this->statusOptions as $option)
                                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                                @endforeach
                                            </select>
                                            @error('status')<div id="travel-inquiry-status-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="travel-inquiry-follow-up" class="form-label">Next follow-up</label>
                                            <input id="travel-inquiry-follow-up" type="datetime-local" class="form-control @error('followUpAt') is-invalid @enderror" wire:model="followUpAt"
                                                @error('followUpAt') aria-invalid="true" aria-describedby="travel-inquiry-follow-up-error" @enderror>
                                            @error('followUpAt')<div id="travel-inquiry-follow-up-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveFollowUp">
                                                <span wire:loading.remove wire:target="saveFollowUp">Save follow-up</span>
                                                <span wire:loading wire:target="saveFollowUp">Saving…</span>
                                            </button>
                                        </div>
                                    </form>
                                </section>
                            @endif

                            <section class="travel-detail-grid__wide" aria-labelledby="travel-inquiry-detail-activity">
                                <h4 id="travel-inquiry-detail-activity">Activity ({{ number_format($selected->activities->count()) }})</h4>
                                @if ($canManage)
                                    <form wire:submit="addActivity" class="row g-3 mb-3" novalidate>
                                        <div class="col-md-3">
                                            <label for="travel-inquiry-activity-type" class="form-label">Type</label>
                                            <select id="travel-inquiry-activity-type" class="form-select" wire:model="activityType">
                                                @foreach (TourInquiryService::NOTE_TYPES as $type)
                                                    <option value="{{ $type }}">{{ $type === 'whatsapp' ? 'WhatsApp' : ucfirst($type) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-9">
                                            <label for="travel-inquiry-activity-note" class="form-label">What happened</label>
                                            <div class="input-group">
                                                <input id="travel-inquiry-activity-note" class="form-control @error('activityNote') is-invalid @enderror" wire:model="activityNote" maxlength="2000" placeholder="Called back, sent the itinerary, customer asked about…"
                                                    @error('activityNote') aria-invalid="true" aria-describedby="travel-inquiry-activity-note-error" @enderror>
                                                <button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled" wire:target="addActivity">Add</button>
                                                @error('activityNote')<div id="travel-inquiry-activity-note-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </form>
                                @endif
                                @if ($selected->activities->isEmpty())
                                    <p class="aureon-muted mb-0">No activity recorded yet.</p>
                                @else
                                    <ul class="travel-detail-rows">
                                        @foreach ($selected->activities as $activity)
                                            <li wire:key="travel-inquiry-activity-{{ $activity->id }}">
                                                <div class="min-w-0">
                                                    <strong class="d-block text-break">{{ $activity->note }}</strong>
                                                    <small class="aureon-muted">{{ $activity->actor?->name ?: 'System' }} &middot; {{ $activity->occurred_at->format('d M Y, H:i') }}</small>
                                                </div>
                                                <span class="travel-status travel-status--muted">{{ $activity->activity_type === 'whatsapp' ? 'WhatsApp' : ucfirst(str_replace('_', ' ', $activity->activity_type)) }}</span>
                                                <span></span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" wire:click="closeDialog">Done</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
