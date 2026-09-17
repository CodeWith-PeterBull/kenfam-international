<?php

/** Coordinates tour-owned itinerary days and activities. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\ItineraryActivityForm;
use App\Modules\TravelTours\Catalog\Livewire\Forms\ItineraryDayForm;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\TourItineraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Present inline editors while services enforce child ownership and order. */
final class TourItineraryEditor extends Component
{
    public ItineraryDayForm $dayForm;

    public ItineraryActivityForm $activityForm;

    #[Locked]
    public int $tourId;

    #[Locked]
    public ?int $editingDayId = null;

    #[Locked]
    public ?int $activityDayId = null;

    #[Locked]
    public ?int $editingActivityId = null;

    public string $editor = '';

    /** Reauthorize module visibility on every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Accept only a persisted, editable tour from the parent editor. */
    public function mount(int $tourId): void
    {
        $this->tourId = $tourId;
        $this->tour();
    }

    /** Open a blank day editor. */
    public function openDay(): void
    {
        $this->tour();
        $this->cancel();
        $this->editor = 'day';
    }

    /** Edit a day resolved under the current tour. */
    public function editDay(int $dayId): void
    {
        $tour = $this->tour();
        $day = $tour->itineraryDays()->whereKey($dayId)->firstOrFail();
        $this->cancel();
        $this->editingDayId = $day->id;
        $this->dayForm->fillFromDay($day);
        $this->editor = 'day';
    }

    /** Persist a day through its typed domain service. */
    public function saveDay(TourItineraryService $service): void
    {
        abort_unless($this->editor === 'day', 404);
        $tour = $this->tour();
        $this->dayForm->validate();
        try {
            $service->saveDay($tour, $this->dayForm->toData(), $this->editingDayId);
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }
        $this->cancel();
        session()->flash('success', 'Itinerary day saved.');
    }

    /** Remove a day only after the browser explicitly confirms the action. */
    public function removeDay(int $dayId, TourItineraryService $service): void
    {
        try {
            $service->removeDay($this->tour(), $dayId);
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }
        $this->cancel();
        session()->flash('success', 'Itinerary day removed.');
    }

    /** Move one day earlier or later. */
    public function moveDay(int $dayId, string $direction, TourItineraryService $service): void
    {
        $service->moveDay($this->tour(), $dayId, $direction);
    }

    /** Open a blank activity editor under the specified day. */
    public function openActivity(int $dayId): void
    {
        $tour = $this->tour();
        $tour->itineraryDays()->whereKey($dayId)->firstOrFail();
        $this->cancel();
        $this->activityDayId = $dayId;
        $this->editor = 'activity';
    }

    /** Edit an activity only through its tour-owned day. */
    public function editActivity(int $dayId, int $activityId): void
    {
        $tour = $this->tour();
        $day = $tour->itineraryDays()->whereKey($dayId)->firstOrFail();
        $activity = $day->activities()->whereKey($activityId)->firstOrFail();
        $this->cancel();
        $this->activityDayId = $dayId;
        $this->editingActivityId = $activityId;
        $this->activityForm->fillFromActivity($activity);
        $this->editor = 'activity';
    }

    /** Persist a validated activity under its locked day. */
    public function saveActivity(TourItineraryService $service): void
    {
        abort_unless($this->editor === 'activity' && $this->activityDayId !== null, 404);
        $tour = $this->tour();
        $this->activityForm->validate();
        try {
            $service->saveActivity($tour, $this->activityDayId, $this->activityForm->toData(), $this->editingActivityId);
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }
        $this->cancel();
        session()->flash('success', 'Itinerary activity saved.');
    }

    /** Remove only an activity belonging to the selected tour and day. */
    public function removeActivity(int $dayId, int $activityId, TourItineraryService $service): void
    {
        $service->removeActivity($this->tour(), $dayId, $activityId);
        $this->cancel();
        session()->flash('success', 'Itinerary activity removed.');
    }

    /** Move an activity within its day. */
    public function moveActivity(int $dayId, int $activityId, string $direction, TourItineraryService $service): void
    {
        $service->moveActivity($this->tour(), $dayId, $activityId, $direction);
    }

    /** Clear inline form state and validation messages. */
    public function cancel(): void
    {
        $this->editor = '';
        $this->editingDayId = null;
        $this->activityDayId = null;
        $this->editingActivityId = null;
        $this->dayForm->resetForCreate();
        $this->activityForm->resetForCreate();
        $this->resetValidation();
    }

    /** Render ordered days and active destination options. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-itinerary-editor', [
            'tour' => $this->tour()->load(['itineraryDays.activities']),
            'destinations' => Destination::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Resolve the parent and authorize mutation for every action. */
    private function tour(): Tour
    {
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('update', $tour);

        return $tour;
    }
}
