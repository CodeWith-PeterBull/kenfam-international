<?php

/** Provides policy-authorized TravelTours category administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourCategoryForm;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\TourCategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Present the category hierarchy and coordinate typed category writes. */
final class TourCategoryManager extends Component
{
    public TourCategoryForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedCategoryId = null;

    /** Reauthorize every Livewire request, including hydration requests. */
    public function boot(): void
    {
        Gate::authorize('viewAny', TourCategory::class);
    }

    #[Computed]
    /** @return Collection<int, TourCategory> */
    public function categories(): Collection
    {
        return TourCategory::query()
            ->with('parent')
            ->withCount(['children', 'tours'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    /** @return Collection<int, TourCategory> */
    public function parentOptions(): Collection
    {
        return $this->categories
            ->filter(fn (TourCategory $category): bool => $category->is_active && $category->id !== $this->selectedCategoryId)
            ->values();
    }

    /** Open a fresh category form only for catalog editors. */
    public function openCreate(): void
    {
        Gate::authorize('create', TourCategory::class);
        $this->closeDialog();
        $this->dialog = 'form';
    }

    /** Load one authorized category for editing. */
    public function openEdit(int $categoryId): void
    {
        $category = TourCategory::query()->findOrFail($categoryId);
        Gate::authorize('update', $category);
        $this->closeDialog();
        $this->selectedCategoryId = $category->getKey();
        $this->form->fillFromCategory($category);
        $this->dialog = 'form';
    }

    /** Persist a validated category through the domain service. */
    public function save(TourCategoryService $categories): void
    {
        abort_unless($this->dialog === 'form', 404);
        $this->form->validate();

        try {
            if ($this->selectedCategoryId === null) {
                Gate::authorize('create', TourCategory::class);
                $categories->create($this->form->toData());
                $message = 'Tour category created.';
            } else {
                $category = TourCategory::query()->findOrFail($this->selectedCategoryId);
                Gate::authorize('update', $category);
                $categories->update($category, $this->form->toData());
                $message = 'Tour category updated.';
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        unset($this->categories, $this->parentOptions);
    }

    /** Change category availability without deleting historical assignments. */
    public function toggleActive(int $categoryId, TourCategoryService $categories): void
    {
        $category = TourCategory::query()->findOrFail($categoryId);
        Gate::authorize('update', $category);

        try {
            $categories->setActive($category, ! $category->is_active);
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $category->is_active ? 'Tour category hidden.' : 'Tour category activated.');
        unset($this->categories, $this->parentOptions);
    }

    /** Close the editor and reset its transient state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedCategoryId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned category manager. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-category-manager');
    }
}
