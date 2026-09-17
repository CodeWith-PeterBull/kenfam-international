<?php

/** Presents a minimal base fare editor inside the tour workspace. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Livewire\Forms\TourBasePriceForm;
use App\Modules\TravelTours\Pricing\Services\TourBasePriceService;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Expose price inspection to editors and mutation only to pricing managers. */
final class TourBasePriceEditor extends Component
{
    public TourBasePriceForm $form;

    #[Locked]
    public int $tourId;

    /** Require catalog visibility on each Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
        Gate::authorize(TravelToursPermission::VIEW_PRICING);
    }

    /** Load the tour's default rate and its unbounded participant fares. */
    public function mount(int $tourId): void
    {
        $this->tourId = $tourId;
        $this->form->fillFromPlan($this->tour()->ratePlans()->where('is_default', true)->with('participantRates')->first());
    }

    /** Save only if the actor owns the dedicated pricing capability. */
    public function save(TourBasePriceService $service): void
    {
        Gate::authorize(TravelToursPermission::MANAGE_PRICING);
        $this->form->validate();
        try {
            $service->save($this->tour(), $this->form->toData());
        } catch (CatalogException $exception) {
            $this->addError('pricing', $exception->getMessage());

            return;
        }
        session()->flash('success', 'Base prices saved.');
    }

    /** Render one plan and its current base fares. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.pricing.tour-base-price-editor', [
            'plan' => $this->tour()->ratePlans()->where('is_default', true)->with('participantRates')->first(),
        ]);
    }

    /** Resolve an authorized tour on every action. */
    private function tour(): Tour
    {
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('view', $tour);

        return $tour;
    }
}
