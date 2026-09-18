<?php

/** Tour-scoped rate plan and participant fare workspace. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Livewire\Forms\RatePlanForm;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Pricing\Services\RatePlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Plans and their fares are edited together in one dialog and saved through
 * RatePlanService in one transaction, so the calculator never observes a
 * half-updated fare set.
 */
final class RatePlanManager extends Component
{
    public RatePlanForm $form;

    #[Locked]
    public int $tourId;

    #[Locked]
    public ?int $editingId = null;

    public string $dialog = '';

    /** Require pricing visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', TourRatePlan::class);
    }

    /** Lock the manager to one tour. */
    public function mount(int $tourId): void
    {
        Tour::query()->findOrFail($tourId);
        $this->tourId = $tourId;
    }

    /** The scoped tour. */
    #[Computed]
    public function tour(): Tour
    {
        return Tour::query()->findOrFail($this->tourId);
    }

    /**
     * Plans with fares and usage counts, default first.
     *
     * @return Collection<int, TourRatePlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return TourRatePlan::query()
            ->where('tour_id', $this->tourId)
            ->with('participantRates')
            ->withCount(['departures', 'bookings', 'pricingRules'])
            ->orderByDesc('is_default')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /** Open the dialog for a new plan. */
    public function openCreate(): void
    {
        Gate::authorize('create', TourRatePlan::class);
        $this->editingId = null;
        $this->form->start((string) config('travel-tours.defaults.currency', 'KES'));
        $this->open();
    }

    /** Open the dialog for an existing plan. */
    public function openEdit(int $planId): void
    {
        $plan = $this->find($planId);
        Gate::authorize('update', $plan);
        $this->editingId = $plan->getKey();
        $this->form->fillFromPlan($plan->load('participantRates'));
        $this->open();
    }

    /** Add a fare row to the open dialog. */
    public function addRate(string $type): void
    {
        $this->form->addRate($type);
    }

    /** Remove a fare row from the open dialog. */
    public function removeRate(int $index): void
    {
        $this->form->removeRate($index);
    }

    /** Save the plan and its fares together. */
    public function save(RatePlanService $service): void
    {
        $this->form->validate();

        try {
            $plan = $this->editingId === null ? null : $this->find($this->editingId);
            Gate::authorize($plan instanceof TourRatePlan ? 'update' : 'create', $plan ?? TourRatePlan::class);
            $service->save($this->tour, $plan, $this->form->toData(), $this->form->toRates());
        } catch (InvalidRateConfiguration|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $this->editingId === null ? 'Rate plan created.' : 'Rate plan updated.');
        $this->closeDialog();
    }

    /** Close the dialog and forget its state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->editingId = null;
        $this->resetErrorBag();
        unset($this->plans);
    }

    /** Render the workspace. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.pricing.rate-plan-manager');
    }

    /** Resolve a plan that belongs to the scoped tour. */
    private function find(int $planId): TourRatePlan
    {
        return TourRatePlan::query()->where('tour_id', $this->tourId)->findOrFail($planId);
    }

    /** Open the form dialog with a clean error bag. */
    private function open(): void
    {
        $this->dialog = 'form';
        $this->resetErrorBag();
    }
}
