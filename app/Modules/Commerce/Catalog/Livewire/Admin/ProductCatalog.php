<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Livewire\Admin;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Livewire\Forms\ProductForm;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Catalog\Services\ProductService;
use App\Modules\Commerce\Exceptions\CatalogException;
use App\Modules\Commerce\Reporting\Filters\ProductReportFilters;
use App\Modules\Commerce\Reporting\Reports\ProductCatalogReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Product CRUD, publication lifecycle, pricing, and gallery administration.
 */
final class ProductCatalog extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'product-q', except: '')]
    public string $search = '';

    #[Url(as: 'product-status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'product-category', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'product-per-page', except: 15)]
    public int $perPage = 15;

    public ProductForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedProductId = null;

    /** @var array<int, mixed> */
    public array $galleryUploads = [];

    /**
     * Enforce catalog visibility for every Livewire request.
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    /**
     * Export the current filtered catalogue as a PDF register (Livewire download).
     */
    public function exportPdf(ProductCatalogReport $report, RecordsSystemActivity $activities): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);
        $actor = $this->actor();
        $filters = ProductReportFilters::fromInputs($this->search, $this->statusFilter, $this->categoryFilter);
        $rendered = $report->render($filters, $actor);

        $activities->record(
            activityType: 'commerce.product.report_exported',
            description: 'Product catalogue report exported',
            actor: $actor,
            properties: ['filters' => $report->filterLabels($filters), 'pages' => $rendered->pageCount],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-reports',
        );

        return response()->streamDownload(static function () use ($rendered): void {
            echo $rendered->contents;
        }, $rendered->filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetProductPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetProductPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetProductPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetProductPage();
    }

    public function updatedGalleryUploads(): void
    {
        Gate::authorize('create', Product::class);
        $this->validate($this->galleryRules());
    }

    /** @return list<ProductStatus> */
    #[Computed]
    public function statuses(): array
    {
        return ProductStatus::cases();
    }

    /** @return Collection<int, ProductCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array{total: int, published: int, drafts: int, featured: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => Product::query()->count(),
            'published' => Product::query()->where('status', ProductStatus::Published->value)->count(),
            'drafts' => Product::query()->where('status', ProductStatus::Draft->value)->count(),
            'featured' => Product::query()->where('is_featured', true)->count(),
        ];
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return $this->filteredProducts()
            ->with(['category', 'stock', 'media'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->validPerPage(), ['*'], 'productPage');
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Product::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $productId): void
    {
        $product = $this->findProduct($productId);
        Gate::authorize('update', $product);
        $this->closeDialog();
        $this->selectedProductId = $product->id;
        $this->form->fillFromProduct($product);
        $this->dialog = 'form';
    }

    /**
     * Fill the internal barcode field with the next auto-generated value.
     *
     * A convenience for officers who want to see and print the store barcode
     * before saving; leaving the field blank auto-generates it on create.
     */
    public function generateInternalBarcode(ProductService $products): void
    {
        Gate::authorize('create', Product::class);
        $this->form->barcode = $products->nextInternalBarcode();
    }

    public function save(ProductService $products): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->validate($this->galleryRules());
        $actor = $this->actor();

        try {
            if ($this->selectedProductId === null) {
                Gate::authorize('create', Product::class);
                $product = $products->createProduct($this->form->payload(), $actor);
                $message = 'Product created as a draft.';
            } else {
                $product = $this->findProduct($this->selectedProductId);
                Gate::authorize('update', $product);
                if (! $this->form->product?->is($product)) {
                    abort(404);
                }

                $product = $products->updateProduct($product, $this->form->payload(), $actor);
                $message = 'Product details updated.';
            }

            if ($this->galleryUploads !== []) {
                $products->addProductImages($product, $this->galleryUploads, $actor);
            }
        } catch (CatalogException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshProducts();
    }

    public function changeStatus(int $productId, string $status, ProductService $products): void
    {
        $product = $this->findProduct($productId);
        Gate::authorize('update', $product);
        $target = ProductStatus::tryFrom($status);
        abort_if($target === null, 422);

        try {
            $products->transition($product, $target, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Product status changed to {$target->label()}.");
        $this->refreshProducts();
    }

    public function removeImage(int $productId, int $mediaId, ProductService $products): void
    {
        $product = $this->findProduct($productId);
        Gate::authorize('update', $product);

        try {
            $updated = $products->removeProductImage($product, $mediaId, $this->actor());
            if ($this->form->product?->is($product)) {
                $this->form->product->setRelation('media', $updated->media);
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Product image removed.');
        $this->refreshProducts();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedProductId = null;
        $this->galleryUploads = [];
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->categoryFilter = '';
        $this->resetProductPage();
    }

    public function money(int $minor): string
    {
        return config('commerce.currency.code', 'KES').' '.number_format(
            $minor / (10 ** (int) config('commerce.currency.decimal_places', 2)),
            (int) config('commerce.currency.decimal_places', 2),
        );
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.product-catalog');
    }

    /** @return array<string, mixed> */
    private function galleryRules(): array
    {
        $limit = max(1, (int) config('commerce.media.product_gallery_limit', 8));
        $maximumKilobytes = max(1, (int) config('commerce.media.upload_max_kilobytes', 5120));

        return [
            'galleryUploads' => ['array', 'max:'.$limit],
            'galleryUploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximumKilobytes],
        ];
    }

    private function filteredProducts(): Builder
    {
        $search = trim($this->search);

        return Product::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('manufacturer_barcode', 'like', "%{$search}%");
                });
            })
            ->when(ProductStatus::tryFrom($this->statusFilter), fn (Builder $query, ProductStatus $status) => $query->where('status', $status->value))
            ->when($this->categories->contains('id', (int) $this->categoryFilter), fn (Builder $query) => $query->where('category_id', (int) $this->categoryFilter));
    }

    private function findProduct(int $productId): Product
    {
        return Product::query()->with(['category', 'stock', 'media'])->findOrFail($productId);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function validPerPage(): int
    {
        return in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
    }

    private function resetProductPage(): void
    {
        $this->resetPage('productPage');
        $this->refreshProducts();
    }

    private function refreshProducts(): void
    {
        unset($this->products, $this->statistics, $this->categories);
    }
}
