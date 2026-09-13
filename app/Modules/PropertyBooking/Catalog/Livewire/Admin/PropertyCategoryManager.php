<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Forms\PropertyCategoryForm;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Services\PropertyCategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Hierarchical property-category management with accessible image replacement. */
final class PropertyCategoryManager extends Component
{
    use WithFileUploads;

    public PropertyCategoryForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedCategoryId = null;

    public mixed $categoryImageUpload = null;

    public string $categoryImageAlt = '';

    public bool $removeCategoryImage = false;

    /** Reauthorize category visibility for every Livewire request. */
    public function boot(): void
    {
        Gate::authorize('viewAny', PropertyCategory::class);
    }

    /** @return Collection<int, PropertyCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return PropertyCategory::query()
            ->with(['parent', 'media'])
            ->withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, PropertyCategory> */
    #[Computed]
    public function parentOptions(): Collection
    {
        return $this->categories
            ->reject(fn (PropertyCategory $category): bool => $category->id === $this->selectedCategoryId)
            ->values();
    }

    /** Open an empty category form. */
    public function openCreate(): void
    {
        Gate::authorize('create', PropertyCategory::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open one authorized category for editing. */
    public function openEdit(int $categoryId): void
    {
        $category = $this->findCategory($categoryId);
        Gate::authorize('update', $category);
        $this->closeDialog();
        $this->selectedCategoryId = $category->getKey();
        $this->form->fillFromCategory($category);
        $image = $category->getFirstMedia('category_image');
        $this->categoryImageAlt = (string) $image?->getCustomProperty('alt_text', '');
        $this->dialog = 'form';
    }

    /** Validate file input as soon as Livewire receives it. */
    public function updatedCategoryImageUpload(): void
    {
        Gate::authorize('create', PropertyCategory::class);
        $this->validate($this->imageRules());
        $this->removeCategoryImage = false;
    }

    /** Persist category metadata and its optional image through domain services. */
    public function save(PropertyCategoryService $categories): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->validate($this->imageRules());
        $actor = $this->actor();

        try {
            if ($this->selectedCategoryId === null) {
                Gate::authorize('create', PropertyCategory::class);
                $category = $categories->create($this->form->payload(), $actor);
                $message = 'Property category created.';
            } else {
                $category = $this->findCategory($this->selectedCategoryId);
                Gate::authorize('update', $category);
                if (! $this->form->category?->is($category)) {
                    abort(404);
                }
                $category = $categories->update($category, $this->form->payload(), $actor);
                $message = 'Property category updated.';
            }

            if ($this->categoryImageUpload !== null) {
                $category = $categories->replaceImage($category, $this->categoryImageUpload, $this->categoryImageAlt, $actor);
            } elseif ($this->removeCategoryImage) {
                $categories->removeImage($category, $actor);
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshCategories();
    }

    /** Toggle category visibility through the guarded service lifecycle. */
    public function toggleActive(int $categoryId, PropertyCategoryService $categories): void
    {
        $category = $this->findCategory($categoryId);
        Gate::authorize('update', $category);

        try {
            $categories->setActive($category, ! $category->is_active, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $category->is_active ? 'Category hidden.' : 'Category activated.');
        $this->refreshCategories();
    }

    /** Close the modal and clear temporary files and validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedCategoryId = null;
        $this->categoryImageUpload = null;
        $this->categoryImageAlt = '';
        $this->removeCategoryImage = false;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned category manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.property-category-manager');
    }

    /** @return array<string, mixed> */
    private function imageRules(): array
    {
        $maximumKilobytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120));

        return [
            'categoryImageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
            'categoryImageAlt' => [Rule::requiredIf($this->categoryImageUpload !== null), 'nullable', 'string', 'max:180'],
        ];
    }

    /** Resolve one category or fail without exposing foreign identifiers. */
    private function findCategory(int $categoryId): PropertyCategory
    {
        return PropertyCategory::query()->with(['parent', 'media'])->findOrFail($categoryId);
    }

    /** Resolve the authenticated actor for service activity attribution. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Invalidate cached category collections after a write. */
    private function refreshCategories(): void
    {
        unset($this->categories, $this->parentOptions);
    }
}
