<?php

/** Shared central and tour-scoped Livewire departure workspace. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\DepartureException;
use App\Modules\TravelTours\Scheduling\Livewire\Forms\DepartureForm;
use App\Modules\TravelTours\Scheduling\Models\DepartureStaffAssignment;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use App\Modules\TravelTours\Scheduling\Services\DepartureManagementService;
use App\Modules\TravelTours\Support\LikePattern;
use App\Modules\TravelTours\Support\TravelToursRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/** Authorize every action and keep central and tour-scoped scheduling identical. */
final class DepartureManager extends Component
{
    use WithPagination;

    /** The central selector lists this many tours by name; larger catalogues filter by tour from the tour editor instead. */
    private const TOUR_OPTION_LIMIT = 200;

    public DepartureForm $form;

    #[Locked]
    public ?int $tourId = null;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $staffDepartureId = null;

    public bool $showForm = false;

    public string $search = '';

    public string $statusFilter = '';

    public string $tourFilter = '';

    public string $staffUserId = '';

    public string $staffRole = 'guide';

    public bool $staffLead = false;

    public string $staffNotes = '';

    /** Require departure visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', TourDeparture::class);
    }

    /** Lock a nested manager to the tour that rendered it. */
    public function mount(?int $tourId = null): void
    {
        if ($tourId !== null) {
            Tour::query()->findOrFail($tourId);
        }
        $this->tourId = $tourId;
        $this->form->start($tourId);
    }

    /** Clear pagination when a filter changes. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Clear pagination when a filter changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** Clear pagination when a filter changes. */
    public function updatedTourFilter(): void
    {
        $this->resetPage();
    }

    /** Open a nearby form with no previous edit state. */
    public function openCreate(): void
    {
        Gate::authorize('create', TourDeparture::class);
        $this->editingId = null;
        $this->form->start($this->tourId);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /** Edit only a departure within this manager's immutable tour scope. */
    public function openEdit(int $id): void
    {
        $departure = $this->departure($id);
        Gate::authorize('update', $departure);
        $this->editingId = $departure->id;
        $this->form->fillFromDeparture($departure);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /** Hide the form without writing an incomplete schedule. */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    /** Open team assignment beside the schedule being managed. */
    public function openStaff(int $id): void
    {
        $departure = $this->departure($id);
        Gate::authorize('update', $departure);
        $this->staffDepartureId = $departure->id;
        $this->staffUserId = '';
        $this->staffRole = 'guide';
        $this->staffLead = false;
        $this->staffNotes = '';
        $this->showForm = false;
        $this->resetErrorBag();
    }

    /** Close team assignment without affecting its persisted rows. */
    public function closeStaff(): void
    {
        $this->staffDepartureId = null;
        $this->resetErrorBag();
    }

    /** Assign an eligible staff account through the transactional service. */
    public function assignStaff(DepartureManagementService $service): void
    {
        $departure = $this->departure($this->staffDepartureId ?? 0);
        Gate::authorize('update', $departure);
        $this->validate([
            'staffUserId' => ['required', 'integer', 'exists:users,id'],
            'staffRole' => ['required', 'in:guide,coordinator,driver,host'],
            'staffNotes' => ['nullable', 'string', 'max:2000'],
        ]);
        $staff = User::query()->role([TravelToursRole::MANAGER, TravelToursRole::BOOKING_AGENT, TravelToursRole::TOUR_EDITOR])
            ->where('is_active', true)->findOrFail((int) $this->staffUserId);
        try {
            $service->assignStaff($departure, $staff, $this->staffRole, $this->staffLead, trim($this->staffNotes) ?: null, $this->actor());
        } catch (DepartureException $exception) {
            $this->addError('staff', $exception->getMessage());

            return;
        }
        $this->staffUserId = '';
        $this->staffNotes = '';
        $this->staffLead = false;
        $this->resetErrorBag('staff');
        session()->flash('success', 'Team assignment saved.');
    }

    /** Remove only the selected departure's assignment. */
    public function removeStaff(int $assignmentId, DepartureManagementService $service): void
    {
        $departure = $this->departure($this->staffDepartureId ?? 0);
        Gate::authorize('update', $departure);
        $service->removeStaff($departure, $assignmentId);
        session()->flash('success', 'Team assignment removed.');
    }

    /** Persist the form through the scheduling service. */
    public function save(DepartureManagementService $service): void
    {
        $departure = $this->editingId ? $this->departure($this->editingId) : null;
        Gate::authorize($departure ? 'update' : 'create', $departure ?? TourDeparture::class);
        $this->form->validate();
        if ($this->tourId !== null && (int) $this->form->tourId !== $this->tourId) {
            abort(403);
        }
        try {
            $departure ? $service->update($departure, $this->form->toData(), $this->actor())
                : $service->create($this->form->toData(), $this->actor());
        } catch (DepartureException $exception) {
            $this->addError('schedule', $exception->getMessage());

            return;
        }
        $this->closeForm();
        session()->flash('success', $departure ? 'Departure updated.' : 'Departure draft created.');
    }

    /** Apply a declared lifecycle transition to an authorized departure. */
    public function changeStatus(int $id, string $status, DepartureManagementService $service): void
    {
        $departure = $this->departure($id);
        Gate::authorize('update', $departure);
        $target = DepartureStatus::tryFrom($status);
        abort_unless($target !== null, 422);
        try {
            $service->transition($departure, $target, $this->actor());
        } catch (DepartureException $exception) {
            $this->addError('schedule', $exception->getMessage());

            return;
        }
        $this->resetErrorBag('schedule');
        session()->flash('success', 'Departure status updated.');
    }

    /** Render bounded results with authoritative capacity figures. */
    public function render(DepartureAvailabilityService $availability): View
    {
        $departures = TourDeparture::query()->with(['tour', 'ratePlan'])
            ->when($this->tourId, fn ($query) => $query->where('tour_id', $this->tourId))
            ->when(! $this->tourId && $this->tourFilter !== '', fn ($query) => $query->where('tour_id', (int) $this->tourFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($query) => $query->where(function ($match): void {
                $match->whereRaw('code '.LikePattern::CLAUSE, [LikePattern::contains($this->search)])
                    ->orWhereHas('tour', fn ($tour) => $tour->whereRaw('name '.LikePattern::CLAUSE, [LikePattern::contains($this->search)]));
            }))
            ->orderBy('starts_at')->paginate(12);
        $capacity = $departures->getCollection()->mapWithKeys(fn (TourDeparture $departure): array => [$departure->id => $availability->check($departure)]);
        $selectedTour = $this->tourId ?? ($this->form->tourId !== '' ? (int) $this->form->tourId : null);

        return view('travel-tours::livewire.admin.scheduling.departure-manager', [
            'departures' => $departures,
            'capacity' => $capacity,
            'tours' => Tour::query()->orderBy('name')->limit(self::TOUR_OPTION_LIMIT)->get(['id', 'ulid', 'name', 'code']),
            'ratePlans' => $selectedTour ? Tour::query()->findOrFail($selectedTour)->ratePlans()->orderBy('name')->get() : collect(),
            'staffOptions' => $this->staffDepartureId ? User::query()->role([TravelToursRole::MANAGER, TravelToursRole::BOOKING_AGENT, TravelToursRole::TOUR_EDITOR])->where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'email']) : collect(),
            'staffAssignments' => $this->staffDepartureId ? DepartureStaffAssignment::query()->with('user')->where('departure_id', $this->staffDepartureId)->orderByDesc('is_lead')->orderBy('role')->get() : collect(),
        ]);
    }

    /** Resolve within a locked tour scope on every request. */
    private function departure(int $id): TourDeparture
    {
        return TourDeparture::query()->when($this->tourId, fn ($query) => $query->where('tour_id', $this->tourId))->findOrFail($id);
    }

    /** Resolve the authenticated operator for audit attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
