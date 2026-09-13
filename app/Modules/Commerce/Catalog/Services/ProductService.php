<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Exceptions\CatalogException;
use App\Modules\Commerce\Inventory\Models\Stock;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Owns catalog writes, publication transitions, actor fields, and audit records.
 */
final readonly class ProductService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCategory(array $attributes, User $actor): ProductCategory
    {
        return $this->database->transaction(function () use ($attributes, $actor): ProductCategory {
            $category = new ProductCategory;
            $category->fill($this->categoryPayload($attributes));
            $this->assertValidCategory($category);
            $this->assertValidCategoryParent($category, $category->parent_id);
            $category->save();

            $this->activities->record(
                activityType: 'commerce.category.created',
                description: "Product category created: {$category->name}",
                actor: $actor,
                subject: $category,
                properties: ['parent_ulid' => $category->parent?->ulid],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-catalog',
            );

            return $category->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCategory(ProductCategory $category, array $attributes, User $actor): ProductCategory
    {
        return $this->database->transaction(function () use ($category, $attributes, $actor): ProductCategory {
            $category = ProductCategory::query()->lockForUpdate()->findOrFail($category->getKey());
            $category->fill($this->categoryPayload($attributes));
            $this->assertValidCategory($category);
            $this->assertValidCategoryParent($category, $category->parent_id);
            $changes = array_keys($category->getDirty());
            $category->save();

            $this->activities->record(
                activityType: 'commerce.category.updated',
                description: "Product category updated: {$category->name}",
                actor: $actor,
                subject: $category,
                properties: ['changed_fields' => $changes],
                source: 'commerce-catalog',
            );

            return $category->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProduct(array $attributes, User $actor): Product
    {
        return $this->database->transaction(function () use ($attributes, $actor): Product {
            $product = new Product;
            $product->forceFill([
                'status' => ProductStatus::Draft,
                'unit_label' => 'item',
                'minimum_order_quantity' => 1,
                'tax_rate_bps' => (int) config('commerce.tax.default_rate_bps', 1600),
                'is_tax_inclusive' => (bool) config('commerce.tax.prices_include_tax', true),
                'track_stock' => true,
                'is_featured' => false,
            ]);
            $product->fill($this->productPayload($attributes));
            $product->forceFill([
                'status' => ProductStatus::Draft,
                'published_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValidProduct($product);
            $product->save();

            // Auto-assign an internal store barcode when none was supplied. The
            // product id now exists, guaranteeing a unique value by construction.
            if (blank($product->barcode)) {
                $product->forceFill(['barcode' => $this->internalBarcodeFor($product)])->save();
            }

            $stock = new Stock;
            $stock->forceFill([
                'product_id' => $product->getKey(),
                'on_hand' => 0,
                'low_stock_threshold' => (int) config('commerce.inventory.default_low_stock_threshold', 5),
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.product.created',
                description: "Product created: {$product->name}",
                actor: $actor,
                subject: $product,
                properties: ['sku' => $product->sku, 'barcode' => $product->barcode, 'status' => $product->status],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-catalog',
            );

            return $product->refresh()->load('stock');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProduct(Product $product, array $attributes, User $actor): Product
    {
        return $this->database->transaction(function () use ($product, $attributes, $actor): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $product->fill($this->productPayload($attributes));
            $product->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValidProduct($product);
            $changes = array_values(array_diff(array_keys($product->getDirty()), ['updated_by']));
            $product->save();

            $this->activities->record(
                activityType: 'commerce.product.updated',
                description: "Product updated: {$product->name}",
                actor: $actor,
                subject: $product,
                properties: ['changed_fields' => $changes],
                source: 'commerce-catalog',
            );

            return $product->refresh()->load('stock');
        });
    }

    /**
     * Move a product through its controlled publication lifecycle.
     */
    public function transition(Product $product, ProductStatus $status, User $actor): Product
    {
        return $this->database->transaction(function () use ($product, $status, $actor): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $previous = $product->status;

            if ($previous === $status) {
                return $product;
            }

            $this->assertValidProduct($product);
            $product->forceFill([
                'status' => $status,
                'published_at' => $status === ProductStatus::Published ? ($product->published_at ?? now()) : null,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.product.status_changed',
                description: "Product status changed to {$status->label()}: {$product->name}",
                actor: $actor,
                subject: $product,
                properties: ['from' => $previous, 'to' => $status],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-catalog',
            );

            return $product->refresh();
        });
    }

    /**
     * Add validated images to the ordered product gallery within its configured cap.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function addProductImages(Product $product, array $uploads, User $actor): Product
    {
        if ($uploads === []) {
            return $product->loadMissing('media');
        }

        $product = Product::query()->findOrFail($product->getKey());
        $limit = max(1, (int) config('commerce.media.product_gallery_limit', 8));
        $currentCount = $product->getMedia('product_gallery')->count();

        if ($currentCount + count($uploads) > $limit) {
            throw new CatalogException("A product gallery may contain no more than {$limit} images.");
        }

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                throw new CatalogException('One of the product images could not be read.');
            }

            $product->addMedia($upload)
                ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
                ->toMediaCollection('product_gallery');
        }

        $this->activities->record(
            activityType: 'commerce.product.media_added',
            description: "Product gallery updated: {$product->name}",
            actor: $actor,
            subject: $product,
            properties: ['images_added' => count($uploads), 'gallery_count' => $currentCount + count($uploads)],
            source: 'commerce-catalog',
        );

        return $product->refresh()->load('media');
    }

    /**
     * Remove one gallery image only when it belongs to the supplied product.
     */
    public function removeProductImage(Product $product, int $mediaId, User $actor): Product
    {
        $product = Product::query()->findOrFail($product->getKey());
        $media = $product->media()
            ->where('collection_name', 'product_gallery')
            ->whereKey($mediaId)
            ->first();

        if (! $media instanceof Media) {
            throw new CatalogException('The selected product image does not exist.');
        }

        $media->delete();

        $this->activities->record(
            activityType: 'commerce.product.media_removed',
            description: "Product gallery image removed: {$product->name}",
            actor: $actor,
            subject: $product,
            properties: ['media_id' => $mediaId],
            source: 'commerce-catalog',
        );

        return $product->refresh()->load('media');
    }

    /**
     * Replace the optional single category image after category persistence.
     */
    public function replaceCategoryImage(ProductCategory $category, UploadedFile $upload, User $actor): ProductCategory
    {
        if (! $upload->isValid()) {
            throw new CatalogException('The category image could not be read.');
        }

        $category = ProductCategory::query()->findOrFail($category->getKey());
        $category->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->toMediaCollection('category_image');

        $this->activities->record(
            activityType: 'commerce.category.media_replaced',
            description: "Category image updated: {$category->name}",
            actor: $actor,
            subject: $category,
            source: 'commerce-catalog',
        );

        return $category->refresh()->load('media');
    }

    /**
     * Remove the optional category image while retaining the category itself.
     */
    public function removeCategoryImage(ProductCategory $category, User $actor): ProductCategory
    {
        $category = ProductCategory::query()->findOrFail($category->getKey());
        if ($category->getFirstMedia('category_image') === null) {
            return $category->loadMissing('media');
        }

        $category->clearMediaCollection('category_image');

        $this->activities->record(
            activityType: 'commerce.category.media_removed',
            description: "Category image removed: {$category->name}",
            actor: $actor,
            subject: $category,
            source: 'commerce-catalog',
        );

        return $category->refresh()->load('media');
    }

    /**
     * Preview the internal barcode the next created product would receive.
     *
     * Used by the catalog form "Generate" affordance before an id exists. The
     * definitive value is still assigned from the real id inside createProduct.
     */
    public function nextInternalBarcode(): string
    {
        return $this->formatInternalBarcode((int) (Product::query()->max('id') ?? 0) + 1);
    }

    /**
     * Build the deterministic internal barcode for a persisted product.
     */
    private function internalBarcodeFor(Product $product): string
    {
        return $this->formatInternalBarcode((int) $product->getKey());
    }

    /**
     * Compose the configured prefix and a zero-padded numeric identifier.
     */
    private function formatInternalBarcode(int $identifier): string
    {
        $prefix = strtoupper(trim((string) config('commerce.barcode.internal_prefix', 'MM')));
        $length = max(1, (int) config('commerce.barcode.internal_pad_length', 10));

        return $prefix.str_pad((string) $identifier, $length, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function categoryPayload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'parent_id', 'name', 'slug', 'description', 'meta_title',
            'meta_description', 'sort_order', 'is_active',
        ]);

        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (! array_key_exists('slug', $payload) && filled($payload['name'] ?? null)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function productPayload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'category_id', 'name', 'slug', 'sku', 'barcode', 'manufacturer_barcode', 'short_description',
            'description', 'specifications', 'unit_label', 'minimum_order_quantity',
            'maximum_order_quantity', 'price_minor', 'sale_price_minor', 'sale_starts_at',
            'sale_ends_at', 'cost_price_minor', 'tax_rate_bps', 'is_tax_inclusive',
            'track_stock', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
            'is_featured', 'meta_title', 'meta_description',
        ]);

        foreach (['name', 'unit_label'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = trim((string) $payload[$field]);
            }
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }
        foreach (['sku', 'barcode', 'manufacturer_barcode'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = strtoupper(trim((string) $payload[$field]));
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    private function assertValidCategoryParent(ProductCategory $category, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentId = (int) $parentId;
        if (! ProductCategory::query()->whereKey($parentId)->exists()) {
            throw new CatalogException('The selected parent category does not exist.');
        }

        if ($category->exists && $parentId === $category->getKey()) {
            throw new CatalogException('A category cannot be its own parent.');
        }

        $visited = [];
        while ($parentId > 0) {
            if (in_array($parentId, $visited, true) || ($category->exists && $parentId === $category->getKey())) {
                throw new CatalogException('The selected parent would create a category cycle.');
            }

            $visited[] = $parentId;
            $parentId = (int) (ProductCategory::query()->whereKey($parentId)->value('parent_id') ?? 0);
        }
    }

    private function assertValidCategory(ProductCategory $category): void
    {
        if (blank($category->name) || blank($category->slug)) {
            throw new CatalogException('Product categories require a name and slug.');
        }
    }

    private function assertValidProduct(Product $product): void
    {
        if (blank($product->name) || blank($product->slug) || blank($product->sku)) {
            throw new CatalogException('Products require a name, slug, and SKU.');
        }

        if ($product->price_minor <= 0) {
            throw new CatalogException('Product prices must be greater than zero.');
        }

        if ($product->sale_price_minor !== null && $product->sale_price_minor >= $product->price_minor) {
            throw new CatalogException('A sale price must be lower than the regular price.');
        }

        if ($product->minimum_order_quantity < 1
            || ($product->maximum_order_quantity !== null
                && $product->maximum_order_quantity < $product->minimum_order_quantity)) {
            throw new CatalogException('Product order quantity limits are invalid.');
        }

        if ($product->sale_starts_at !== null && $product->sale_ends_at !== null
            && $product->sale_ends_at->lessThanOrEqualTo($product->sale_starts_at)) {
            throw new CatalogException('The sale end must be later than the sale start.');
        }
    }
}
