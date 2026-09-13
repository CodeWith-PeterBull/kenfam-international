<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Database\Seeders\CatalogDemoSeeder;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Verifies contextual fixture completeness, selection, and non-destructive replacement.
 */
final class CommerceContextualDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_context_definitions_are_complete_globally_unique_and_media_ready(): void
    {
        $seen = ['sku' => [], 'slug' => [], 'barcode' => [], 'category' => []];
        $productCount = 0;
        $categoryCount = 0;

        foreach (CatalogDemoSeeder::supportedContexts() as $context) {
            $definition = CatalogDemoSeeder::contextDefinition($context);
            $this->assertSame($context, $definition['key']);
            $this->assertGreaterThanOrEqual(10, count($definition['products']));
            $this->assertLessThanOrEqual(12, count($definition['products']));

            $contextSkus = array_column($definition['products'], 'sku');
            $transactionSkus = [
                $definition['transactions']['web_order_sku'],
                ...$definition['transactions']['pos_order_skus'],
            ];
            $this->assertCount(3, $transactionSkus);

            foreach ($transactionSkus as $sku) {
                $this->assertContains($sku, $contextSkus);
            }

            foreach ($definition['categories'] as $category) {
                $this->assertUniqueAcrossContexts($seen['category'], $category['slug'], 'category slug');
                $seen['category'][$category['slug']] = $context;
                $this->assertDemoImageExists($category['image']);
                $categoryCount++;
            }

            foreach ($definition['products'] as $product) {
                foreach (['sku', 'slug', 'barcode'] as $key) {
                    $this->assertUniqueAcrossContexts($seen[$key], $product[$key], $key);
                    $seen[$key][$product[$key]] = $context;
                }

                $this->assertContains($product['category'], array_column($definition['categories'], 'slug'));
                $this->assertIsInt($product['price_minor']);
                $this->assertGreaterThanOrEqual(0, $product['price_minor']);
                $this->assertIsInt($product['cost_price_minor']);
                $this->assertGreaterThanOrEqual(0, $product['cost_price_minor']);
                $this->assertNotEmpty($product['specifications']);

                if ($context !== CatalogDemoSeeder::DEFAULT_CONTEXT) {
                    $this->assertGreaterThanOrEqual(2, count($product['images']));
                    $this->assertLessThanOrEqual(3, count($product['images']));
                }

                foreach ($product['images'] as $image) {
                    $this->assertDemoImageExists($image);
                }

                $productCount++;
            }
        }

        $this->assertSame(130, $productCount);
        $this->assertSame(68, $categoryCount);
    }

    public function test_all_context_catalogs_can_coexist_and_rerun_without_resetting_stock(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        foreach (CatalogDemoSeeder::supportedContexts() as $context) {
            $this->invokeSeeder(CatalogDemoSeeder::class, ['context' => $context]);
        }

        $this->assertSame(68, ProductCategory::query()->count());
        $this->assertSame(130, Product::query()->count());
        $this->assertSame(130, Stock::query()->count());
        $this->assertSame(130, StockMovement::query()->count());
        $this->assertSame(332, Media::query()->count());

        $product = Product::query()->where('sku', 'AUR-CIT-LT01')->firstOrFail();
        $product->stock()->update(['on_hand' => 9]);

        $this->invokeSeeder(CatalogDemoSeeder::class, ['context' => 'computers-it']);

        $this->assertSame(130, Product::query()->count());
        $this->assertSame(130, StockMovement::query()->count());
        $this->assertSame(9, $product->stock()->firstOrFail()->on_hand);
        $this->assertCount(2, $product->fresh()->getMedia('product_gallery'));
    }

    public function test_shoe_store_context_seeds_three_angle_galleries_and_coherent_transactions(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->artisan('commerce:demo-seed', ['context' => 'shoe-store'])
            ->expectsOutputToContain('Commerce demo context [shoe-store] seeded (keep existing).')
            ->assertSuccessful();

        $this->assertSame(12, Product::query()->count());
        $this->assertSame(6, ProductCategory::query()->count());
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(42, Media::query()->count());
        $this->assertEqualsCanonicalizing(
            ['AUR-SHO-LS01', 'AUR-SHO-CV05', 'AUR-SHO-FB09'],
            OrderItem::query()->with('product')->get()->pluck('product.sku')->all(),
        );

        Product::query()->get()->each(function (Product $product): void {
            $this->assertCount(3, $product->getMedia('product_gallery'));
        });
    }

    public function test_rerun_refreshes_copied_media_when_a_module_source_asset_changes(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->invokeSeeder(CatalogDemoSeeder::class, ['context' => 'hardware-construction']);

        $product = Product::query()->where('sku', 'AUR-HWC-PT01')->firstOrFail();
        $storedMedia = $product->getMedia('product_gallery')->firstOrFail();
        $sourcePath = app_path('Modules/Commerce/Resources/demo/products/contexts/hardware-construction/weatherguard-exterior-paint-20l-01.png');

        file_put_contents($storedMedia->getPath(), 'stale demonstration media');
        $this->assertNotSame(hash_file('sha256', $sourcePath), hash_file('sha256', $storedMedia->getPath()));

        $this->invokeSeeder(CatalogDemoSeeder::class, ['context' => 'hardware-construction']);

        $refreshedGallery = $product->fresh()->getMedia('product_gallery');
        $this->assertCount(2, $refreshedGallery);
        $this->assertSame(hash_file('sha256', $sourcePath), hash_file('sha256', $refreshedGallery->firstOrFail()->getPath()));
    }

    public function test_command_seeds_an_alternate_context_with_coherent_transactions(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->artisan('commerce:demo-seed', ['context' => 'computers-it'])
            ->expectsOutputToContain('Commerce demo context [computers-it] seeded (keep existing).')
            ->assertSuccessful();

        $this->assertSame(12, Product::query()->count());
        $this->assertSame(6, ProductCategory::query()->count());
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(30, Media::query()->count());
        $this->assertEqualsCanonicalizing(
            ['AUR-CIT-LT01', 'AUR-CIT-KB06', 'AUR-CIT-MS07'],
            OrderItem::query()->with('product')->get()->pluck('product.sku')->all(),
        );
    }

    public function test_archive_existing_replaces_the_active_catalog_without_deleting_history(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->invokeSeeder(CommerceDemoSeeder::class);

        $defaultProduct = Product::query()->where('sku', 'AUR-PH-X101')->firstOrFail();
        $defaultStock = $defaultProduct->stock()->firstOrFail()->on_hand;
        $orderIds = Order::query()->pluck('id')->all();
        $movementCount = StockMovement::query()->count();

        $this->invokeSeeder(CommerceDemoSeeder::class, [
            'context' => 'computers-it',
            'archiveExisting' => true,
        ]);

        $this->assertSame(22, Product::query()->count());
        $this->assertSame(12, Product::query()->where('status', ProductStatus::Published->value)->count());
        $this->assertSame(10, Product::query()->where('status', ProductStatus::Archived->value)->count());
        $this->assertSame($defaultStock, $defaultProduct->stock()->firstOrFail()->on_hand);
        $this->assertEqualsCanonicalizing($orderIds, Order::query()->pluck('id')->all());
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(3, OrderItem::query()->whereNotNull('product_id')->count());
        $this->assertSame($movementCount + 12, StockMovement::query()->count());
        $this->assertSame(ProductStatus::Archived, $defaultProduct->fresh()->status);
    }

    public function test_preflight_collision_fails_before_archive_mutates_existing_products(): void
    {
        $product = Product::factory()->published()->create([
            'slug' => 'computers-it-horizon-14-business-laptop',
            'sku' => 'ADOPTER-OWNED-001',
            'barcode' => 'ADOPTER-001',
        ]);

        try {
            $this->invokeSeeder(CatalogDemoSeeder::class, [
                'context' => 'computers-it',
                'archiveExisting' => true,
            ]);
            $this->fail('A conflicting context was seeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('belongs to another product', $exception->getMessage());
        }

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_command_rejects_an_unknown_context_without_mutation(): void
    {
        $this->artisan('commerce:demo-seed', ['context' => 'unknown-shop'])
            ->expectsOutputToContain('Unsupported Commerce demo context [unknown-shop].')
            ->assertExitCode(2);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_categories', 0);
    }

    /**
     * Invoke a parameterized Laravel seeder through the same container path as callWith().
     *
     * @param  class-string  $seederClass
     * @param  array<string, mixed>  $parameters
     */
    private function invokeSeeder(string $seederClass, array $parameters = []): void
    {
        $seeder = app($seederClass);
        $seeder->setContainer(app());
        $seeder->__invoke($parameters);
    }

    /**
     * Assert a stable value has not already been claimed by another context.
     *
     * @param  array<string, string>  $seen
     */
    private function assertUniqueAcrossContexts(array $seen, string $value, string $label): void
    {
        $this->assertArrayNotHasKey(
            $value,
            $seen,
            "Duplicate {$label} [{$value}] appears in multiple contexts.",
        );
    }

    /**
     * Assert one source asset is a readable PNG image.
     */
    private function assertDemoImageExists(string $relativePath): void
    {
        $path = app_path('Modules/Commerce/Resources/demo/products/'.$relativePath);
        $this->assertFileExists($path);
        $this->assertSame('image/png', mime_content_type($path));
    }
}
