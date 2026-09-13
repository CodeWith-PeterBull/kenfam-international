<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Seeders;

use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

/**
 * Seeds one deterministic contextual catalog with media and reconciled stock.
 */
final class CatalogDemoSeeder extends Seeder
{
    public const DEFAULT_CONTEXT = 'default-mixed';

    private const OPENING_NOTE = 'Commerce demonstration opening balance';

    /** @var array<string, string> */
    private const CONTEXT_FILES = [
        self::DEFAULT_CONTEXT => 'default-mixed.php',
        'computers-it' => 'computers-it.php',
        'hardware-construction' => 'hardware-construction.php',
        'boutique-fashion' => 'boutique-fashion.php',
        'pharmacy-health' => 'pharmacy-health.php',
        'supermarket-fmcg' => 'supermarket-fmcg.php',
        'beauty-personal-care' => 'beauty-personal-care.php',
        'automotive-parts' => 'automotive-parts.php',
        'agrovet-farm' => 'agrovet-farm.php',
        'office-bookshop' => 'office-bookshop.php',
        'shoe-store' => 'shoe-store.php',
    ];

    /**
     * Populate the public demonstration catalog without resetting live balances.
     */
    public function run(
        string $context = self::DEFAULT_CONTEXT,
        bool $archiveExisting = false,
    ): void {
        $definition = self::contextDefinition($context);
        $this->preflight($definition);

        $actorId = User::query()->where('email', 'admin@aureon.test')->value('id')
            ?? User::query()->value('id');
        $actorId = $actorId === null ? null : (int) $actorId;

        [$categories, $products] = DB::transaction(function () use ($definition, $archiveExisting, $actorId): array {
            if ($archiveExisting) {
                Product::query()->update([
                    'status' => ProductStatus::Archived->value,
                    'updated_by' => $actorId,
                ]);
            }

            $categories = $this->seedCategories($definition['categories']);
            $products = [];

            foreach ($definition['products'] as $productDefinition) {
                $products[$productDefinition['sku']] = $this->seedProduct(
                    definition: $productDefinition,
                    category: $categories[$productDefinition['category']],
                    actorId: $actorId,
                );
            }

            return [$categories, $products];
        });

        foreach ($definition['products'] as $productDefinition) {
            $this->syncMedia(
                $products[$productDefinition['sku']],
                'product_gallery',
                $productDefinition['images'],
            );
        }

        foreach ($definition['categories'] as $categoryDefinition) {
            $this->syncMedia(
                model: $categories[$categoryDefinition['slug']],
                collection: 'category_image',
                relativePaths: [$categoryDefinition['image']],
            );
        }
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function seedCategories(array $definitions): array
    {
        $categories = [];

        foreach ($definitions as $definition) {
            $category = ProductCategory::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'parent_id' => null,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'meta_title' => $definition['name'].' | Aureon Store',
                    'meta_description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );

            $categories[$definition['slug']] = $category;
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProduct(array $definition, ProductCategory $category, ?int $actorId): Product
    {
        $product = Product::withTrashed()->where('sku', $definition['sku'])->first() ?? new Product;
        if ($product->trashed()) {
            $product->restore();
        }

        $product->fill([
            'category_id' => $category->getKey(),
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'sku' => $definition['sku'],
            'barcode' => $definition['barcode'],
            'manufacturer_barcode' => $definition['manufacturer_barcode'],
            'short_description' => $definition['short_description'],
            'description' => $definition['description'],
            'specifications' => $definition['specifications'],
            'unit_label' => $definition['unit_label'],
            'minimum_order_quantity' => $definition['minimum_order_quantity'],
            'maximum_order_quantity' => $definition['maximum_order_quantity'],
            'price_minor' => $definition['price_minor'],
            'sale_price_minor' => $definition['sale_price_minor'],
            'sale_starts_at' => $definition['sale_price_minor'] === null ? null : now()->subDays(7),
            'sale_ends_at' => $definition['sale_price_minor'] === null ? null : now()->addMonths(3),
            'cost_price_minor' => $definition['cost_price_minor'],
            'tax_rate_bps' => $definition['tax_rate_bps']
                ?? (int) config('commerce.tax.default_rate_bps', 1600),
            'is_tax_inclusive' => $definition['is_tax_inclusive']
                ?? (bool) config('commerce.tax.prices_include_tax', true),
            'track_stock' => true,
            'weight_grams' => $definition['weight_grams'],
            'length_mm' => $definition['dimensions'][0],
            'width_mm' => $definition['dimensions'][1],
            'height_mm' => $definition['dimensions'][2],
            'is_featured' => $definition['is_featured'],
            'meta_title' => $definition['name'].' | Aureon Store',
            'meta_description' => $definition['short_description'],
        ]);
        $product->forceFill([
            'status' => ProductStatus::Published,
            'published_at' => now()->subDays(30),
            'created_by' => $product->created_by ?? $actorId,
            'updated_by' => $actorId,
        ])->save();

        $stock = Stock::query()->where('product_id', $product->getKey())->lockForUpdate()->first();
        if (! $stock instanceof Stock) {
            $stock = new Stock;
            $stock->forceFill([
                'product_id' => $product->getKey(),
                'on_hand' => $definition['stock'],
                'low_stock_threshold' => $definition['low_stock_threshold'],
                'updated_by' => $actorId,
            ])->save();
        }

        $hasOpeningMovement = StockMovement::query()
            ->where('product_id', $product->getKey())
            ->where('note', self::OPENING_NOTE)
            ->exists();

        if (! $hasOpeningMovement) {
            $movement = new StockMovement;
            $movement->forceFill([
                'product_id' => $product->getKey(),
                'type' => StockMovementType::Opening,
                'quantity_delta' => $stock->on_hand,
                'balance_before' => 0,
                'balance_after' => $stock->on_hand,
                'reference_type' => null,
                'reference_id' => null,
                'note' => self::OPENING_NOTE,
                'created_by' => $actorId,
                'created_at' => now()->subDays(30),
            ])->save();
        }

        return $product->refresh();
    }

    /**
     * Repair missing demo media while avoiding duplicates on normal reruns.
     *
     * @param  list<string>  $relativePaths
     */
    private function syncMedia(HasMedia $model, string $collection, array $relativePaths): void
    {
        $existing = $model->getMedia($collection)->values();
        $usable = $existing->count() === count($relativePaths)
            && $existing->every(function ($media, int $index) use ($relativePaths): bool {
                $storedPath = $media->getPath();
                $sourcePath = $this->demoMediaPath($relativePaths[$index]);

                return is_file($storedPath)
                    && is_file($sourcePath)
                    && hash_file('sha256', $storedPath) === hash_file('sha256', $sourcePath);
            });

        if ($usable) {
            return;
        }

        $model->clearMediaCollection($collection);

        foreach ($relativePaths as $relativePath) {
            $path = $this->demoMediaPath($relativePath);
            if (! is_file($path)) {
                throw new RuntimeException("Commerce demonstration media is missing: {$relativePath}");
            }

            $model->addMedia($path)
                ->preservingOriginal()
                ->usingName(pathinfo($relativePath, PATHINFO_FILENAME))
                ->toMediaCollection($collection);
        }
    }

    /**
     * Resolve one trusted module-owned demonstration media source.
     */
    private function demoMediaPath(string $relativePath): string
    {
        return __DIR__.'/../../Resources/demo/products/'.$relativePath;
    }

    /**
     * Return the supported context keys in command-display order.
     *
     * @return list<string>
     */
    public static function supportedContexts(): array
    {
        return array_keys(self::CONTEXT_FILES);
    }

    /**
     * Load one allowlisted context definition.
     *
     * @return array<string, mixed>
     */
    public static function contextDefinition(string $context): array
    {
        $file = self::CONTEXT_FILES[$context] ?? null;
        if ($file === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Commerce demo context [%s]. Supported contexts: %s.',
                $context,
                implode(', ', self::supportedContexts()),
            ));
        }

        $definition = require __DIR__.'/Data/'.$file;
        if (! is_array($definition) || ($definition['key'] ?? null) !== $context) {
            throw new RuntimeException("Commerce demonstration context [{$context}] is malformed.");
        }

        return $definition;
    }

    /**
     * Validate all selected data and media before any archive or catalog write.
     *
     * @param  array<string, mixed>  $definition
     */
    private function preflight(array $definition): void
    {
        foreach (['categories', 'products', 'transactions'] as $key) {
            if (! isset($definition[$key]) || ! is_array($definition[$key])) {
                throw new RuntimeException("Commerce demonstration context is missing [{$key}] data.");
            }
        }

        if (count($definition['products']) < 10 || count($definition['products']) > 12) {
            throw new RuntimeException('Commerce demonstration contexts must define between 10 and 12 products.');
        }

        $categorySlugs = [];
        $mediaPaths = [];

        foreach ($definition['categories'] as $category) {
            foreach (['slug', 'name', 'description', 'sort_order', 'image'] as $key) {
                if (! array_key_exists($key, $category)) {
                    throw new RuntimeException("Commerce demonstration category is missing [{$key}].");
                }
            }

            foreach (['slug', 'name', 'description', 'image'] as $key) {
                if (! is_string($category[$key]) || trim($category[$key]) === '') {
                    throw new RuntimeException("Commerce demonstration category [{$key}] must be a non-empty string.");
                }
            }

            $this->assertNonNegativeInteger($category['sort_order'], 'category sort order');

            $categorySlugs[] = $category['slug'];
            $mediaPaths[] = $category['image'];
        }

        $this->assertUnique($categorySlugs, 'category slug');

        $skus = [];
        $slugs = [];
        $barcodes = [];

        foreach ($definition['products'] as $product) {
            foreach ([
                'category', 'name', 'slug', 'sku', 'barcode', 'manufacturer_barcode',
                'short_description', 'description', 'specifications', 'unit_label',
                'minimum_order_quantity', 'maximum_order_quantity', 'price_minor',
                'sale_price_minor', 'cost_price_minor', 'tax_rate_bps',
                'is_tax_inclusive', 'stock', 'low_stock_threshold', 'is_featured',
                'weight_grams', 'dimensions', 'images',
            ] as $key) {
                if (! array_key_exists($key, $product)) {
                    throw new RuntimeException("Commerce demonstration product is missing [{$key}].");
                }
            }

            foreach (['category', 'name', 'slug', 'sku', 'barcode', 'short_description', 'description', 'unit_label'] as $key) {
                if (! is_string($product[$key]) || trim($product[$key]) === '') {
                    throw new RuntimeException("Commerce demonstration product [{$key}] must be a non-empty string.");
                }
            }

            if (! in_array($product['category'], $categorySlugs, true)) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] references an unknown category.");
            }

            $requiredGalleryCount = ($definition['key'] ?? null) === self::DEFAULT_CONTEXT ? 1 : 2;
            if (! is_array($product['images']) || count($product['images']) < $requiredGalleryCount) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] requires at least {$requiredGalleryCount} gallery image(s).");
            }

            foreach (['price_minor', 'cost_price_minor', 'stock', 'low_stock_threshold', 'weight_grams'] as $key) {
                $this->assertNonNegativeInteger($product[$key], "product {$key}");
            }

            if ($product['sale_price_minor'] !== null) {
                $this->assertNonNegativeInteger($product['sale_price_minor'], 'product sale_price_minor');
                if ($product['sale_price_minor'] >= $product['price_minor']) {
                    throw new RuntimeException("Commerce demonstration product [{$product['sku']}] sale price must be lower than its regular price.");
                }
            }

            if (! is_int($product['minimum_order_quantity']) || $product['minimum_order_quantity'] < 1) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] minimum order quantity must be positive.");
            }

            if ($product['maximum_order_quantity'] !== null
                && (! is_int($product['maximum_order_quantity'])
                    || $product['maximum_order_quantity'] < $product['minimum_order_quantity'])) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] maximum order quantity is invalid.");
            }

            if (! is_array($product['dimensions']) || count($product['dimensions']) !== 3) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] requires three dimensions.");
            }
            foreach ($product['dimensions'] as $dimension) {
                $this->assertNonNegativeInteger($dimension, 'product dimension');
            }

            if (! is_array($product['specifications']) || $product['specifications'] === []) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] requires specifications.");
            }

            if (! is_bool($product['is_featured'])
                || ($product['is_tax_inclusive'] !== null && ! is_bool($product['is_tax_inclusive']))) {
                throw new RuntimeException("Commerce demonstration product [{$product['sku']}] has invalid boolean policy data.");
            }

            if ($product['tax_rate_bps'] !== null) {
                $this->assertNonNegativeInteger($product['tax_rate_bps'], 'product tax_rate_bps');
                if ($product['tax_rate_bps'] > 10_000) {
                    throw new RuntimeException("Commerce demonstration product [{$product['sku']}] tax rate exceeds 100 percent.");
                }
            }

            $skus[] = $product['sku'];
            $slugs[] = $product['slug'];
            $barcodes[] = $product['barcode'];
            array_push($mediaPaths, ...$product['images']);
        }

        $this->assertUnique($skus, 'SKU');
        $this->assertUnique($slugs, 'product slug');
        $this->assertUnique($barcodes, 'barcode');

        $webOrderSku = $definition['transactions']['web_order_sku'] ?? null;
        $posOrderSkus = $definition['transactions']['pos_order_skus'] ?? null;
        if (! is_string($webOrderSku)
            || ! is_array($posOrderSkus)
            || count($posOrderSkus) !== 2
            || ! array_is_list($posOrderSkus)
            || count(array_filter($posOrderSkus, 'is_string')) !== 2) {
            throw new RuntimeException('Commerce demonstration transaction data requires one web and two POS product SKUs.');
        }

        $transactionSkus = [$webOrderSku, ...$posOrderSkus];
        $this->assertUnique($transactionSkus, 'transaction SKU');

        foreach ($transactionSkus as $sku) {
            if (! in_array($sku, $skus, true)) {
                throw new RuntimeException("Commerce demonstration transaction SKU [{$sku}] is not in the selected catalog.");
            }
        }

        foreach (array_unique($mediaPaths) as $relativePath) {
            if (! is_string($relativePath) || str_contains($relativePath, '..')) {
                throw new RuntimeException('Commerce demonstration media paths must be safe relative paths.');
            }

            if (! is_file($this->demoMediaPath($relativePath))) {
                throw new RuntimeException("Commerce demonstration media is missing: {$relativePath}");
            }
        }

        foreach ($definition['products'] as $product) {
            foreach (['slug', 'barcode', 'manufacturer_barcode'] as $column) {
                $value = $product[$column] ?? null;
                if ($value === null) {
                    continue;
                }

                $conflict = Product::withTrashed()
                    ->where($column, $value)
                    ->where('sku', '!=', $product['sku'])
                    ->exists();
                if ($conflict) {
                    throw new RuntimeException("Commerce demonstration {$column} [{$value}] belongs to another product.");
                }
            }
        }
    }

    /**
     * Reject duplicate values in one context before it reaches the database.
     *
     * @param  list<string>  $values
     */
    private function assertUnique(array $values, string $label): void
    {
        if (count($values) !== count(array_unique($values))) {
            throw new RuntimeException("Commerce demonstration context contains a duplicate {$label}.");
        }
    }

    /**
     * Require an integer suitable for persisted quantities or minor units.
     */
    private function assertNonNegativeInteger(mixed $value, string $label): void
    {
        if (! is_int($value) || $value < 0) {
            throw new RuntimeException("Commerce demonstration {$label} must be a non-negative integer.");
        }
    }

    /**
     * Build the complete product payload shared by each trusted data file.
     *
     * @param  array{0: int, 1: int, 2: int}  $dimensions
     * @param  list<string>  $images
     * @param  array<string, array<string, string>>  $specifications
     * @return array<string, mixed>
     */
    public static function productDefinition(
        string $category,
        string $name,
        string $slug,
        string $sku,
        string $barcode,
        int $priceMinor,
        ?int $salePriceMinor,
        int $costPriceMinor,
        int $stock,
        int $lowStockThreshold,
        bool $featured,
        int $weightGrams,
        array $dimensions,
        array $images,
        string $shortDescription,
        array $specifications,
        string $unitLabel = 'item',
        int $minimumOrderQuantity = 1,
        ?int $maximumOrderQuantity = 5,
        ?string $manufacturerBarcode = null,
        ?int $taxRateBps = null,
        ?bool $isTaxInclusive = null,
    ): array {
        return [
            'category' => $category,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'barcode' => $barcode,
            'manufacturer_barcode' => $manufacturerBarcode,
            'unit_label' => $unitLabel,
            'minimum_order_quantity' => $minimumOrderQuantity,
            'maximum_order_quantity' => $maximumOrderQuantity,
            'price_minor' => $priceMinor,
            'sale_price_minor' => $salePriceMinor,
            'cost_price_minor' => $costPriceMinor,
            'stock' => $stock,
            'low_stock_threshold' => $lowStockThreshold,
            'is_featured' => $featured,
            'weight_grams' => $weightGrams,
            'dimensions' => $dimensions,
            'images' => $images,
            'short_description' => $shortDescription,
            'description' => $shortDescription.' Built as a demonstration product for the reusable Aureon Commerce storefront, with complete catalog, pricing, inventory, and presentation data ready for adopter replacement.',
            'specifications' => $specifications,
            'tax_rate_bps' => $taxRateBps,
            'is_tax_inclusive' => $isTaxInclusive,
        ];
    }
}
