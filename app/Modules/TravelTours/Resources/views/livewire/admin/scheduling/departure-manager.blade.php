<section class="travel-departures" aria-labelledby="travel-departures-title">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @error('schedule')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <div class="card aureon-panel">
        <div class="card-header travel-child-toolbar">
            <div><h3 id="travel-departures-title" class="card-title mb-1">{{ $tourId ? 'Scheduled dates' : 'Departure schedule' }}</h3><p>{{ $tourId ? 'Manage this tour’s dates and seats.' : 'Manage dates and seats across all tours.' }}</p></div>
            @can('create', \App\Modules\TravelTours\Scheduling\Models\TourDeparture::class)
                <button type="button" class="btn btn-primary" wire:click="openCreate"><i class="ti ti-plus me-2" aria-hidden="true"></i>Add departure</button>
            @endcan
        </div>

        @if($showForm)
            <form wire:submit="save" class="travel-departure-form border-bottom" novalidate>
                <div class="travel-departure-form__heading"><div><h4>{{ $editingId ? 'Edit departure' : 'New departure' }}</h4><p>Times are entered in the departure’s local timezone and stored in UTC.</p></div><button type="button" class="btn btn-icon btn-outline-secondary" wire:click="closeForm" aria-label="Close departure form" title="Close"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                <div class="row g-3">
                    @if(!$tourId)
                        <div class="col-md-6"><label for="departure-tour" class="form-label">Tour</label><select id="departure-tour" class="form-select @error('form.tourId') is-invalid @enderror" wire:model.live="form.tourId" @disabled($editingId)><option value="">Choose tour</option>@foreach($tours as $tour)<option value="{{ $tour->id }}">{{ $tour->name }} ({{ $tour->code }})</option>@endforeach</select>@error('form.tourId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @endif
                    <div class="col-md-6"><label for="departure-code" class="form-label">Departure code</label><input id="departure-code" class="form-control @error('form.code') is-invalid @enderror" wire:model.blur="form.code" maxlength="80" placeholder="EGYPT-2026-01" required>@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-timezone" class="form-label">Local timezone</label><input id="departure-timezone" class="form-control @error('form.timezone') is-invalid @enderror" wire:model.blur="form.timezone" list="departure-timezones" autocomplete="off" required><datalist id="departure-timezones"><option value="Africa/Nairobi"><option value="Africa/Cairo"><option value="Africa/Johannesburg"><option value="Asia/Jerusalem"><option value="Europe/London"><option value="Australia/Sydney"></datalist>@error('form.timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-start" class="form-label">Starts locally</label><input id="departure-start" type="datetime-local" class="form-control @error('form.localStart') is-invalid @enderror" wire:model="form.localStart" required>@error('form.localStart')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-end" class="form-label">Ends locally</label><input id="departure-end" type="datetime-local" class="form-control @error('form.localEnd') is-invalid @enderror" wire:model="form.localEnd" required>@error('form.localEnd')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-booking-open" class="form-label">Booking opens <span class="aureon-muted">(optional)</span></label><input id="departure-booking-open" type="datetime-local" class="form-control @error('form.localBookingOpen') is-invalid @enderror" wire:model="form.localBookingOpen">@error('form.localBookingOpen')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-booking-close" class="form-label">Booking closes <span class="aureon-muted">(optional)</span></label><input id="departure-booking-close" type="datetime-local" class="form-control @error('form.localBookingClose') is-invalid @enderror" wire:model="form.localBookingClose">@error('form.localBookingClose')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-sm-6 col-lg-3"><label for="departure-capacity" class="form-label">Seats</label><input id="departure-capacity" type="number" min="1" max="100000" class="form-control @error('form.capacity') is-invalid @enderror" wire:model="form.capacity" required>@error('form.capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-sm-6 col-lg-3"><label for="departure-minimum" class="form-label">Minimum group</label><input id="departure-minimum" type="number" min="1" max="100000" class="form-control @error('form.minimumParticipants') is-invalid @enderror" wire:model="form.minimumParticipants" required>@error('form.minimumParticipants')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6 col-lg-3"><label for="departure-rate" class="form-label">Rate plan</label><select id="departure-rate" class="form-select @error('form.ratePlanId') is-invalid @enderror" wire:model="form.ratePlanId"><option value="">Tour default</option>@foreach($ratePlans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->currency }})</option>@endforeach</select>@error('form.ratePlanId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6 col-lg-3"><label for="departure-mode" class="form-label">Confirmation</label><select id="departure-mode" class="form-select @error('form.bookingMode') is-invalid @enderror" wire:model="form.bookingMode"><option value="">Use tour setting</option>@foreach(\App\Modules\TravelTours\Bookings\Enums\BookingMode::cases() as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach</select>@error('form.bookingMode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-meeting" class="form-label">Meeting instructions</label><textarea id="departure-meeting" rows="3" class="form-control" wire:model.blur="form.meetingInstructions"></textarea>@error('form.meetingInstructions')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="departure-notes" class="form-label">Internal notes</label><textarea id="departure-notes" rows="3" class="form-control" wire:model.blur="form.operationalNotes"></textarea>@error('form.operationalNotes')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                </div>
                <div class="travel-editor-actions"><button type="button" class="btn btn-outline-secondary" wire:click="closeForm">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Save changes' : 'Create draft' }}</button></div>
            </form>
        @endif

        @if($staffDepartureId)
            <div class="travel-departure-form border-bottom" aria-labelledby="departure-staff-title">
                <div class="travel-departure-form__heading"><div><h4 id="departure-staff-title">Departure team</h4><p>Assign a guide, coordinator, driver or host to this departure.</p></div><button type="button" class="btn btn-icon btn-outline-secondary" wire:click="closeStaff" aria-label="Close team assignment" title="Close"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                @error('staff')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                <form wire:submit="assignStaff" class="row g-3 align-items-end" novalidate>
                    <div class="col-md-5"><label for="departure-staff-user" class="form-label">Team member</label><select id="departure-staff-user" class="form-select @error('staffUserId') is-invalid @enderror" wire:model="staffUserId"><option value="">Choose active operator</option>@foreach($staffOptions as $option)<option value="{{ $option->id }}">{{ $option->name }} ({{ $option->email }})</option>@endforeach</select>@error('staffUserId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><label for="departure-staff-role" class="form-label">Role</label><select id="departure-staff-role" class="form-select" wire:model="staffRole">@foreach(['guide' => 'Guide', 'coordinator' => 'Coordinator', 'driver' => 'Driver', 'host' => 'Host'] as $role => $label)<option value="{{ $role }}">{{ $label }}</option>@endforeach</select>@error('staffRole')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-md-2"><div class="form-check form-switch mb-2"><input id="departure-staff-lead" type="checkbox" class="form-check-input" wire:model="staffLead"><label for="departure-staff-lead" class="form-check-label">Lead</label></div></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="assignStaff">Assign</button></div>
                    <div class="col-12"><label for="departure-staff-notes" class="form-label">Assignment note <span class="aureon-muted">(optional)</span></label><input id="departure-staff-notes" class="form-control" wire:model.blur="staffNotes" maxlength="2000">@error('staffNotes')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                </form>
                <div class="travel-child-list mt-4">
                    @forelse($staffAssignments as $assignment)
                        <div class="travel-child-entry" wire:key="departure-staff-{{ $assignment->id }}"><div class="travel-child-entry__head"><div><strong>{{ $assignment->user?->name ?? 'Former operator' }} @if($assignment->is_lead)<span class="travel-status travel-status--active ms-1">Lead</span>@endif</strong><p>{{ ucfirst($assignment->role) }}@if($assignment->notes) &middot; {{ $assignment->notes }}@endif</p></div><button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeStaff({{ $assignment->id }})" wire:confirm="Remove this team assignment?" aria-label="Remove {{ $assignment->user?->name ?? 'operator' }}" title="Remove"><i class="ti ti-trash" aria-hidden="true"></i></button></div></div>
                    @empty<div class="travel-child-empty">No team members assigned yet.</div>@endforelse
                </div>
            </div>
        @endif

        <div class="card-body border-bottom travel-departure-filters">
            <div><label for="departure-search" class="form-label">Search</label><input id="departure-search" type="search" class="form-control" placeholder="Code or tour" wire:model.live.debounce.350ms="search"></div>
            @if(!$tourId)<div><label for="departure-tour-filter" class="form-label">Tour</label><select id="departure-tour-filter" class="form-select" wire:model.live="tourFilter"><option value="">All tours</option>@foreach($tours as $tour)<option value="{{ $tour->id }}">{{ $tour->name }}</option>@endforeach</select></div>@endif
            <div><label for="departure-status-filter" class="form-label">Status</label><select id="departure-status-filter" class="form-select" wire:model.live="statusFilter"><option value="">All statuses</option>@foreach(\App\Modules\TravelTours\Scheduling\Enums\DepartureStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
        </div>

        <div class="table-responsive"><table class="table travel-departure-table mb-0"><thead><tr><th>Departure</th>@if(!$tourId)<th>Tour</th>@endif<th>Local travel window</th><th>Rate / mode</th><th>Seats</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($departures as $departure)
                @php($availability = $capacity[$departure->id])
                @php($next = match($departure->status->value) {'draft' => ['open' => 'Open sales', 'cancelled' => 'Cancel'], 'open' => ['guaranteed' => 'Guarantee', 'closed' => 'Close sales', 'cancelled' => 'Cancel'], 'guaranteed' => ['closed' => 'Close sales', 'departed' => 'Mark departed', 'cancelled' => 'Cancel'], 'closed' => ['open' => 'Reopen sales', 'cancelled' => 'Cancel'], 'departed' => ['completed' => 'Complete'], default => []})
                <tr wire:key="departure-row-{{ $departure->id }}">
                    <td><strong class="d-block">{{ $departure->code }}</strong><small class="aureon-muted">{{ $departure->timezone }}</small></td>
                    @if(!$tourId)<td><a href="{{ route('travel-tours.admin.catalog.tours.edit', ['tour' => $departure->tour, 'section' => 'departures']) }}">{{ $departure->tour->name }}</a></td>@endif
                    <td><span class="d-block">{{ $departure->starts_at->timezone($departure->timezone)->format('d M Y, H:i') }}</span><small class="aureon-muted">to {{ $departure->ends_at->timezone($departure->timezone)->format('d M Y, H:i') }}</small></td>
                    <td><span class="d-block">{{ $departure->ratePlan?->name ?: 'Tour default' }}</span><small class="aureon-muted">{{ $departure->booking_mode?->label() ?: 'Tour setting' }}</small></td>
                    <td><strong class="d-block">{{ $availability->availableSeats }} / {{ $availability->capacity }} available</strong><small class="aureon-muted">{{ $availability->bookedSeats }} booked &middot; {{ $availability->heldSeats }} held</small></td>
                    <td><span class="travel-status {{ in_array($departure->status->value, ['open', 'guaranteed'], true) ? 'travel-status--active' : 'travel-status--muted' }}">{{ $departure->status->label() }}</span></td>
                    <td><div class="travel-admin-row-actions justify-content-end">@can('update', $departure)<button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEdit({{ $departure->id }})" aria-label="Edit {{ $departure->code }}" title="Edit"><i class="ti ti-pencil" aria-hidden="true"></i></button><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openStaff({{ $departure->id }})" aria-label="Assign team to {{ $departure->code }}" title="Departure team"><i class="ti ti-users" aria-hidden="true"></i></button>@if($next)<div class="dropdown"><button type="button" class="btn btn-icon btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Change status of {{ $departure->code }}" title="Change status"><i class="ti ti-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end">@foreach($next as $status => $label)<li><button type="button" class="dropdown-item" wire:click="changeStatus({{ $departure->id }}, '{{ $status }}')">{{ $label }}</button></li>@endforeach</ul></div>@endif @endcan</div></td>
                </tr>
            @empty<tr><td colspan="{{ $tourId ? 6 : 7 }}"><div class="travel-admin-empty"><i class="ti ti-calendar-event" aria-hidden="true"></i><strong>No departures found</strong><span>Add a date or adjust the filters.</span></div></td></tr>@endforelse
        </tbody></table></div>
        @if($departures->hasPages())<div class="card-footer">{{ $departures->links() }}</div>@endif
    </div>
</section>
