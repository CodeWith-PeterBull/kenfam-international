<div class="travel-editor-form" aria-label="Itinerary editor">
    @if(session('success'))<div class="alert alert-success mx-4 mt-3 mb-0" role="status">{{ session('success') }}</div>@endif
    @error('management')<div class="alert alert-danger mx-4 mt-3 mb-0" role="alert">{{ $message }}</div>@enderror
    <section class="travel-editor-section" aria-labelledby="itinerary-days-heading">
        <div class="travel-child-toolbar">
            <div><h4 id="itinerary-days-heading">Day-by-day itinerary</h4><p>{{ $tour->itineraryDays->count() }} of {{ $tour->duration_days }} tour days planned</p></div>
            <button type="button" class="btn btn-primary" wire:click="openDay" @disabled($tour->itineraryDays->count() >= $tour->duration_days)><i class="ti ti-plus me-1" aria-hidden="true"></i>Add day</button>
        </div>
        <div class="travel-child-list">
            @forelse($tour->itineraryDays as $day)
                <section class="travel-child-entry" wire:key="itinerary-day-{{ $day->id }}" aria-labelledby="itinerary-day-{{ $day->id }}">
                    <div class="travel-child-entry__head">
                        <div><h5 id="itinerary-day-{{ $day->id }}">Day {{ $day->day_number }}: {{ $day->title }}</h5><p>{{ $day->description ?: 'No narrative added yet.' }}</p>@if($day->meals || $day->accommodation)<p class="travel-child-entry__meta">@if($day->meals)Meals: {{ implode(', ', $day->meals) }}@endif @if($day->accommodation) | Stay: {{ $day->accommodation }}@endif</p>@endif</div>
                        <div class="travel-child-actions">
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveDay({{ $day->id }}, 'up')" @disabled($day->day_number === 1) aria-label="Move day {{ $day->day_number }} earlier" title="Move earlier"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveDay({{ $day->id }}, 'down')" @disabled($day->day_number === $tour->itineraryDays->count()) aria-label="Move day {{ $day->day_number }} later" title="Move later"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="editDay({{ $day->id }})" aria-label="Edit day {{ $day->day_number }}" title="Edit day"><i class="ti ti-edit" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeDay({{ $day->id }})" wire:confirm="Remove this day and its activities?" aria-label="Remove day {{ $day->day_number }}" title="Remove day"><i class="ti ti-trash" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="travel-activity-list">
                        @foreach($day->activities as $activity)
                            <div class="travel-child-entry" wire:key="itinerary-activity-{{ $activity->id }}">
                                <div class="travel-child-entry__head">
                                    <div><strong>{{ $activity->title }}</strong><p>@if($activity->starts_at_local){{ substr($activity->starts_at_local, 0, 5) }}@if($activity->ends_at_local)-{{ substr($activity->ends_at_local, 0, 5) }}@endif | @endif{{ $activity->location_name ?: ($activity->is_optional ? 'Optional activity' : 'Included activity') }}</p>@if($activity->description)<p>{{ $activity->description }}</p>@endif</div>
                                    <div class="travel-child-actions">
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveActivity({{ $day->id }}, {{ $activity->id }}, 'up')" @disabled($activity->sequence === 1) aria-label="Move {{ $activity->title }} earlier" title="Move earlier"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="moveActivity({{ $day->id }}, {{ $activity->id }}, 'down')" @disabled($activity->sequence === $day->activities->count()) aria-label="Move {{ $activity->title }} later" title="Move later"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="editActivity({{ $day->id }}, {{ $activity->id }})" aria-label="Edit {{ $activity->title }}" title="Edit activity"><i class="ti ti-edit" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeActivity({{ $day->id }}, {{ $activity->id }})" wire:confirm="Remove this activity?" aria-label="Remove {{ $activity->title }}" title="Remove activity"><i class="ti ti-trash" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" wire:click="openActivity({{ $day->id }})"><i class="ti ti-plus me-1" aria-hidden="true"></i>Add activity</button>
                    </div>
                </section>
            @empty
                <div class="travel-child-empty">No itinerary days yet.</div>
            @endforelse
        </div>

    </section>

    @if($editor === 'day')
        <div class="modal fade show d-block travel-child-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="itinerary-day-form-title" wire:keydown.escape.window="cancel">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="saveDay" novalidate>
                    <div class="modal-header"><div><h3 id="itinerary-day-form-title" class="modal-title fs-18">{{ $editingDayId ? 'Edit itinerary day' : 'Add itinerary day' }}</h3><p class="aureon-muted mb-0 fs-12">Plan a single day within this {{ $tour->duration_days }}-day tour.</p></div><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="cancel" aria-label="Close itinerary day form"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                    <div class="col-md-8"><label class="form-label" for="itinerary-day-title">Title</label><input id="itinerary-day-title" class="form-control @error('dayForm.title') is-invalid @enderror" wire:model.blur="dayForm.title" maxlength="200" required autofocus>@error('dayForm.title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label class="form-label" for="itinerary-day-meals">Meals</label><input id="itinerary-day-meals" class="form-control" wire:model.blur="dayForm.meals" placeholder="Breakfast, Dinner">@error('dayForm.meals')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="itinerary-day-description">Narrative</label><textarea id="itinerary-day-description" class="form-control" wire:model.blur="dayForm.description" rows="4"></textarea>@error('dayForm.description')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="itinerary-day-start">Start destination</label><select id="itinerary-day-start" class="form-select" wire:model="dayForm.startDestinationId"><option value="">None</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select>@error('dayForm.startDestinationId')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="itinerary-day-end">End destination</label><select id="itinerary-day-end" class="form-select" wire:model="dayForm.endDestinationId"><option value="">None</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select>@error('dayForm.endDestinationId')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="itinerary-day-accommodation">Accommodation</label><input id="itinerary-day-accommodation" class="form-control" wire:model.blur="dayForm.accommodation" maxlength="240">@error('dayForm.accommodation')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="cancel">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveDay">Save day</button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @elseif($editor === 'activity')
        @php($activityDay = $tour->itineraryDays->firstWhere('id', $activityDayId))
        <div class="modal fade show d-block travel-child-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="itinerary-activity-form-title" wire:keydown.escape.window="cancel">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <form class="modal-content" wire:submit="saveActivity" novalidate>
                    <div class="modal-header"><div><h3 id="itinerary-activity-form-title" class="modal-title fs-18">{{ $editingActivityId ? 'Edit activity' : 'Add activity' }}</h3><p class="aureon-muted mb-0 fs-12">{{ $activityDay ? 'Day '.$activityDay->day_number.': '.$activityDay->title : 'Selected itinerary day' }}</p></div><button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="cancel" aria-label="Close activity form"><i class="ti ti-x" aria-hidden="true"></i></button></div>
                    <div class="modal-body">
                        @error('management')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="row g-3">
                    <div class="col-md-8"><label class="form-label" for="itinerary-activity-title">Title</label><input id="itinerary-activity-title" class="form-control @error('activityForm.title') is-invalid @enderror" wire:model.blur="activityForm.title" maxlength="200" required autofocus>@error('activityForm.title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-6 col-md-2"><label class="form-label" for="itinerary-activity-start">Starts</label><input id="itinerary-activity-start" type="time" class="form-control" wire:model="activityForm.startsAtLocal">@error('activityForm.startsAtLocal')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-6 col-md-2"><label class="form-label" for="itinerary-activity-end">Ends</label><input id="itinerary-activity-end" type="time" class="form-control" wire:model="activityForm.endsAtLocal">@error('activityForm.endsAtLocal')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="itinerary-activity-description">Description</label><textarea id="itinerary-activity-description" class="form-control" rows="3" wire:model.blur="activityForm.description"></textarea>@error('activityForm.description')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="itinerary-activity-location">Location</label><input id="itinerary-activity-location" class="form-control" wire:model.blur="activityForm.locationName" maxlength="200"></div>
                    <div class="col-md-6"><label class="form-label" for="itinerary-activity-destination">Destination</label><select id="itinerary-activity-destination" class="form-select" wire:model="activityForm.destinationId"><option value="">None</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="itinerary-activity-lat">Latitude</label><input id="itinerary-activity-lat" class="form-control" wire:model.blur="activityForm.latitude">@error('activityForm.latitude')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-6 col-md-3"><label class="form-label" for="itinerary-activity-lng">Longitude</label><input id="itinerary-activity-lng" class="form-control" wire:model.blur="activityForm.longitude">@error('activityForm.longitude')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
                    <div class="col-6 col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="itinerary-activity-included" type="checkbox" class="form-check-input" wire:model="activityForm.isIncluded"><label class="form-check-label" for="itinerary-activity-included">Included</label></div></div>
                    <div class="col-6 col-md-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="itinerary-activity-optional" type="checkbox" class="form-check-input" wire:model="activityForm.isOptional"><label class="form-check-label" for="itinerary-activity-optional">Optional</label></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="cancel">Cancel</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveActivity">Save activity</button></div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
