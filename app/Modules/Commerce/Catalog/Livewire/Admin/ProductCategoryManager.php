<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\Commerce\Catalog\Livewire\Forms\CategoryForm;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Catalog\Services\ProductService;
use App\Modules\Commerce\Exceptions\CatalogException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Hierarchical category management with a replaceable single category image.
 */
final class ProductCategoryManager extends Component
{
    use WithFileUploads;

    public CategoryForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedCategoryId = null;

    public mixed $categoryImageUpload = null;

    public bool $removeCategoryImage = false;

    public function boot(): void
    {
        Gate::authorize('viewAny', ProductCategory::class);
    }

    public function updatedCategoryImageUpload(): void
    {
        Gate::authorize('create', ProductCategory::class);
        $this->validate($this->imageRules());
        $this->removeCategoryImage = false;
    }

    /** @return Collection<int, ProductCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->with(['parent', 'media'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, ProductCategory> */
    #[Computed]
    public function parentOptions(): Collection
    {
        return $this->categories
            ->reject(fn (ProductCategory $category): bool => $category->id === $this->selectedCategoryId)
            ->values();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', ProductCategory::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $categoryId): void
    {
        $category = $this->findCategory($categoryId);
        Gate::authorize('update', $category);
        $this->closeDialog();
        $this->selectedCategoryId = $category->id;
        $this->form->fillFromCategory($category);
        $this->dialog = 'form';
    }

    public function save(ProductService $products): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->validate($this->imageRules());
        $actor = $this->actor();

        try {
            if ($this->selectedCategoryId === null) {
                Gate::authorize('create', ProductCategory::class);
                $category = $products->createCategory($this->form->payload(), $actor);
                $message = 'Product category created.';
            } else {
                $category = $this->findCategory($this->selectedCategoryId);
                Gate::authorize('update', $category);
                if (! $this->form->category?->is($category)) {
                    abort(404);
                }

                $category = $products->updateCategory($category, $this->form->payload(), $actor);
                $message = 'Product category updated.';
            }

            if ($this->categoryImageUpload !== null) {
                $products->replaceCategoryImage($category, $this->categoryImageUpload, $actor);
            } elseif ($this->removeCategoryImage) {
                $products->removeCategoryImage($category, $actor);
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshCategories();
    }

    public function toggleActive(int $categoryId, ProductService $products): void
    {
        $category = $this->findCategory($categoryId);
        Gate::authorize('update', $category);

        try {
            $products->updateCategory($category, ['is_active' => ! $category->is_active], $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $category->is_active ? 'Category hidden.' : 'Category activated.');
        $this->refreshCategories();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedCategoryId = null;
        $this->categoryImageUpload = null;
        $this->removeCategoryImage = false;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.product-category-manager');
    }

    /** @return array<string, mixed> */
    private function imageRules(): array
    {
        $maximumKilobytes = max(1, (int) config('commerce.media.upload_max_kilobytes', 5120));

        return [
            'categoryImageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
        ];
    }

    private function findCategory(int $categoryId): ProductCategory
    {
        return ProductCategory::query()->with(['parent', 'media'])->findOrFail($categoryId);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function refreshCategories(): void
    {
        unset($this->categories, $this->parentOptions);
    }
}
