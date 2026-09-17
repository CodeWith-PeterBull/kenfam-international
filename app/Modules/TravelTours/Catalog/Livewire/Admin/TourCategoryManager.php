<?php

/** Provides policy-authorized TravelTours category administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Admin;

use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Livewire\Forms\TourCategoryForm;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Services\TourCategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Present the category hierarchy and coordinate typed category writes.
 *
 * Reads are policy-scoped per request; every write reauthorizes its specific
 * record and hands the transaction to the domain service. Ordering, visibility,
 * and detail inspection are separate actions so a viewer can inspect a node
 * without ever seeing a control that would be refused.
 */
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
    /**
     * Load the hierarchy in tree order with the counts the row controls need.
     *
     * Rows are flattened depth-first so every child follows its parent and
     * siblings stay contiguous; a flat `sort_order` sort would interleave
     * levels and make the ordering arrows appear not to work. Each row carries
     * a transient `depth` for indentation. The published-tour and active-child
     * counts let the visibility switch state its blocker before the service
     * refuses, instead of after.
     *
     * @return Collection<int, TourCategory>
     */
    public function categories(): Collection
    {
        $byParent = TourCategory::query()
            ->with('parent')
            ->withCount([
                'children',
                'tours',
                'children as active_children_count' => fn (Builder $query) => $query->where('is_active', true),
                'tours as published_tours_count' => fn (Builder $query) => $query->where('travel_tours.status', PublicationStatus::Published->value),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TourCategory $category): string => (string) $category->parent_id);

        $ordered = new Collection;
        $append = function (?int $parentId, int $depth) use (&$append, $byParent, $ordered): void {
            foreach ($byParent->get((string) $parentId, new Collection) as $category) {
                $category->setAttribute('depth', $depth);
                $ordered->push($category);
                $append($category->id, $depth + 1);
            }
        };
        $append(null, 0);

        return $ordered;
    }

    #[Computed]
    /**
     * Offer only active nodes other than the one being edited as parents.
     *
     * @return Collection<int, TourCategory>
     */
    public function parentOptions(): Collection
    {
        return $this->categories
            ->filter(fn (TourCategory $category): bool => $category->is_active && $category->id !== $this->selectedCategoryId)
            ->values();
    }

    #[Computed]
    /** Resolve the category open in the details dialog with its relations. */
    public function selectedCategory(): ?TourCategory
    {
        if ($this->dialog !== 'details' || $this->selectedCategoryId === null) {
            return null;
        }

        return TourCategory::query()
            ->with([
                'parent',
                'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'tours' => fn ($query) => $query->orderBy('name'),
            ])
            ->find($this->selectedCategoryId);
    }

    #[Computed]
    /**
     * Describe each sibling's position so the view can disable boundary arrows.
     *
     * @return array<int, array{first: bool, last: bool}>
     */
    public function positions(): array
    {
        $positions = [];
        foreach ($this->categories->groupBy(fn (TourCategory $category): string => (string) $category->parent_id) as $siblings) {
            $siblings = $siblings->values();
            foreach ($siblings as $index => $sibling) {
                $positions[$sibling->id] = ['first' => $index === 0, 'last' => $index === $siblings->count() - 1];
            }
        }

        return $positions;
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

    /** Open the read-only detail view for anyone allowed to inspect the node. */
    public function openDetails(int $categoryId): void
    {
        $category = TourCategory::query()->findOrFail($categoryId);
        Gate::authorize('view', $category);
        $this->closeDialog();
        $this->selectedCategoryId = $category->getKey();
        $this->dialog = 'details';
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
        $this->refreshHierarchy();
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
            $this->refreshHierarchy();

            return;
        }

        session()->flash('success', $category->is_active ? 'Tour category hidden.' : 'Tour category activated.');
        $this->refreshHierarchy();
    }

    /** Move one category a step among its siblings through the domain service. */
    public function move(int $categoryId, string $direction, TourCategoryService $categories): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $category = TourCategory::query()->findOrFail($categoryId);
        Gate::authorize('update', $category);

        try {
            $categories->move($category, $direction);
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->refreshHierarchy();
    }

    /** Close any dialog and reset its transient state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedCategoryId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
        unset($this->selectedCategory);
    }

    /** Render the module-owned category manager. */
    public function render(): View
    {
        return view('travel-tours::livewire.admin.catalog.tour-category-manager');
    }

    /** Drop every cached projection after a write so the next render re-reads. */
    private function refreshHierarchy(): void
    {
        unset($this->categories, $this->parentOptions, $this->positions, $this->selectedCategory);
    }
}
