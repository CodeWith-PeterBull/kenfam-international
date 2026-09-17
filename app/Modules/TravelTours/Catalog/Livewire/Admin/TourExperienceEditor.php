<?php

/** Coordinates structured experience copy, FAQs and priced extras. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourContentItemForm;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourExtraForm;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourFaqForm;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\TourContentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Delegate all child writes to a typed, tour-owned content service. */
final class TourExperienceEditor extends Component
{
    public TourContentItemForm $itemForm;

    public TourFaqForm $faqForm;

    public TourExtraForm $extraForm;

    #[Locked]
    public int $tourId;

    #[Locked]
    public ?int $editingId = null;

    public string $editor = '';

    /** Reauthorize catalog visibility on every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', Tour::class);
    }

    /** Accept a persisted, editable tour from its parent editor. */
    public function mount(int $tourId): void
    {
        $this->tourId = $tourId;
        $this->tour();
        $this->extraForm->resetForCreate();
    }

    /** Open a blank form for one recognized content family. */
    public function open(string $kind): void
    {
        $this->tour();
        abort_unless(in_array($kind, ['item', 'faq', 'extra'], true), 404);
        $this->cancel();
        $this->editor = $kind;
    }

    /** Load only a child owned by this tour. */
    public function edit(string $kind, int $id): void
    {
        $tour = $this->tour();
        abort_unless(in_array($kind, ['item', 'faq', 'extra'], true), 404);
        $child = match ($kind) {
            'item' => $tour->contentItems()->whereKey($id)->firstOrFail(),
            'faq' => $tour->faqs()->whereKey($id)->firstOrFail(),
            'extra' => $tour->extras()->whereKey($id)->firstOrFail(),
        };
        $this->cancel();
        $this->editingId = $id;
        match ($kind) {
            'item' => $this->itemForm->fillFromItem($child),
            'faq' => $this->faqForm->fillFromFaq($child),
            'extra' => $this->extraForm->fillFromExtra($child),
        };
        $this->editor = $kind;
    }

    /** Save one typed child after validating its active form. */
    public function save(TourContentService $service): void
    {
        $tour = $this->tour();
        abort_unless(in_array($this->editor, ['item', 'faq', 'extra'], true), 404);
        try {
            match ($this->editor) {
                'item' => $this->saveItem($service, $tour),
                'faq' => $this->saveFaq($service, $tour),
                'extra' => $this->saveExtra($service, $tour),
            };
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }
        $this->cancel();
        session()->flash('success', 'Tour experience saved.');
    }

    /** Remove a child only from this tour. Extras are archived by the service. */
    public function remove(string $kind, int $id, TourContentService $service): void
    {
        $tour = $this->tour();
        match ($kind) {
            'item' => $service->removeItem($tour, $id),
            'faq' => $service->removeFaq($tour, $id),
            'extra' => $service->removeExtra($tour, $id),
            default => abort(404),
        };
        $this->cancel();
        session()->flash('success', 'Tour experience updated.');
    }

    /** Move one item, FAQ, or extra within its ordered group. */
    public function move(string $kind, int $id, string $direction, TourContentService $service): void
    {
        $tour = $this->tour();
        match ($kind) {
            'item' => $service->moveItem($tour, $id, $direction),
            'faq' => $service->moveFaq($tour, $id, $direction),
            'extra' => $service->moveExtra($tour, $id, $direction),
            default => abort(404),
        };
    }

    /** Reset the transient form without modifying the tour. */
    public function cancel(): void
    {
        $this->editor = '';
        $this->editingId = null;
        $this->itemForm->resetForCreate();
        $this->faqForm->resetForCreate();
        $this->extraForm->resetForCreate();
        $this->resetValidation();
    }

    /** Render the ordered, tour-owned experience collections. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-experience-editor', [
            'tour' => $this->tour()->load(['contentItems', 'faqs', 'extras']),
        ]);
    }

    /** Validate and save a typed content item. */
    private function saveItem(TourContentService $service, Tour $tour): void
    {
        $this->itemForm->validate();
        $service->saveItem($tour, $this->itemForm->toData(), $this->editingId);
    }

    /** Validate and save a typed FAQ. */
    private function saveFaq(TourContentService $service, Tour $tour): void
    {
        $this->faqForm->validate();
        $service->saveFaq($tour, $this->faqForm->toData(), $this->editingId);
    }

    /** Validate and save an exact-money extra with actor attribution. */
    private function saveExtra(TourContentService $service, Tour $tour): void
    {
        $this->extraForm->validate();
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $service->saveExtra($tour, $this->extraForm->toData(), $actor, $this->editingId);
    }

    /** Resolve the parent and authorize modification on every action. */
    private function tour(): Tour
    {
        $tour = Tour::query()->findOrFail($this->tourId);
        Gate::authorize('update', $tour);

        return $tour;
    }
}
