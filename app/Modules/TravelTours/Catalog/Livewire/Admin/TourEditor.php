<?php

/** Coordinates the tour basics, route, itinerary, and experience workspaces. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourAssignmentForm;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourForm;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\TourAssignmentService;
use App\Modules\TravelTours\Catalog\Services\TourService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Authorize every edit action and delegate persistence to typed services. */
final class TourEditor extends Component
{
    public TourForm $form;

    public TourAssignmentForm $assignments;

    #[Locked]
    public ?int $tourId = null;

    public string $tab = 'basics';

    public string $pendingDestinationId = '';

    /** Require catalog visibility on every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Load the requested tour after the route controller authorizes it. */
    public function mount(?int $tourId = null): void
    {
        $this->tourId = $tourId;
        if ($tourId !== null) {
            $tour = $this->tour();
            $this->form->fillFromTour($tour);
            $this->assignments->fillFromTour($tour);
        }
    }

    #[Computed]
    /** Resolve the selected authorized tour for display. */
    public function currentTour(): ?Tour
    {
        return $this->tourId === null ? null : $this->tour();
    }

    /** Switch only between implemented editor tabs. */
    public function switchTab(string $tab): void
    {
        abort_unless(in_array($tab, ['basics', 'route', 'itinerary', 'experience', 'pricing', 'media', 'publication'], true), 404);
        abort_if($tab !== 'basics' && $this->tourId === null, 404);
        $this->tab = $tab;
    }

    /** Persist tour basics through the typed service and open route editing after creation. */
    public function saveBasics(TourService $service): mixed
    {
        $tour = null;
        if ($this->tourId === null) {
            Gate::authorize('create', Tour::class);
        } else {
            $tour = $this->tour();
        }
        $this->form->validate();
        try {
            if ($this->tourId === null) {
                $tour = $service->create($this->form->toData(), $this->actor());
                session()->flash('success', 'Tour draft created. Add its route next.');

                return redirect()->route('travel-tours.admin.catalog.tours.edit', $tour->ulid);
            }
            $service->update($tour, $this->form->toData(), $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return null;
        }
        unset($this->currentTour);
        session()->flash('success', 'Tour basics saved.');

        return null;
    }

    /** Persist an entire validated route through the tour-owned assignment service. */
    public function saveRoute(TourAssignmentService $service): void
    {
        $tour = $this->tour();
        $this->assignments->validate();
        try {
            $service->replace($tour, $this->assignments->toData());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }
        unset($this->currentTour);
        session()->flash('success', 'Tour route saved.');
    }

    /** Add an existing active destination to the route draft. */
    public function addDestination(): void
    {
        $this->tour();
        $id = (int) $this->pendingDestinationId;
        if ($id < 1 || ! Destination::query()->where('is_active', true)->whereKey($id)->exists()
            || collect($this->assignments->destinationRows)->contains(fn ($row) => (int) $row['destination_id'] === $id)) {
            $this->addError('route', 'Choose an active destination that is not already in this route.');

            return;
        }
        $this->assignments->destinationRows[] = ['destination_id' => $id, 'role' => 'visit', 'is_overnight' => false];
        $this->pendingDestinationId = '';
        $this->resetErrorBag('route');
    }

    /** Remove one unsaved route row without mutating persisted assignments. */
    public function removeDestination(int $index): void
    {
        $this->tour();
        abort_unless(array_key_exists($index, $this->assignments->destinationRows), 404);
        array_splice($this->assignments->destinationRows, $index, 1);
    }

    /** Reorder unsaved route rows by one position. */
    public function moveDestination(int $index, string $direction): void
    {
        $this->tour();
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! array_key_exists($index, $this->assignments->destinationRows) || ! array_key_exists($swap, $this->assignments->destinationRows)) {
            return;
        }
        [$this->assignments->destinationRows[$index], $this->assignments->destinationRows[$swap]] = [$this->assignments->destinationRows[$swap], $this->assignments->destinationRows[$index]];
    }

    /** Render the implemented tabs with only active assignment options. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-editor', [
            'categoryOptions' => TourCategory::query()->active()->orderBy('name')->get(),
            'destinationOptions' => Destination::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Resolve a persisted tour and authorize modification each time. */
    private function tour(): Tour
    {
        abort_if($this->tourId === null, 404);
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('update', $tour);

        return $tour;
    }

    /** Resolve the authenticated actor for service attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
