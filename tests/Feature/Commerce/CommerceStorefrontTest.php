<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Storefront\Livewire\CatalogBrowser;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the first public Commerce catalog and product-detail vertical slice.
 */
final class CommerceStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_catalog_and_slug_product_routes_render_public_storefront_assets(): void
    {
        $product = $this->publishedProduct('Arc ANC Headphones', 'arc-anc-headphones', 18_500_00, 8);
        $product->addMedia(UploadedFile::fake()->image('arc-headphones.png', 600, 600))
            ->toMediaCollection('product_gallery');

        $this->get(route('commerce.storefront.catalog.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.storefront.catalog-browser')
            ->assertSee('Aureon Commerce')
            ->assertSee($product->name)
            ->assertSee('Theme settings');

        $url = route('commerce.storefront.products.show', ['product' => $product->slug]);
        $this->assertStringContainsString('/shop/products/'.$product->slug, $url);
        $this->assertStringNotContainsString($product->ulid, $url);

        $this->get($url)
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->sku)
            ->assertSee('Specifications')
            ->assertSee($product->getFirstMediaUrl('product_gallery'), false);
    }

    public function test_public_queries_exclude_every_non_visible_product_state(): void
    {
        $visible = $this->publishedProduct('Visible Product', 'visible-product', 25_000_00, 5);
        $draft = $this->product('Draft Product', 'draft-product', ProductStatus::Draft);
        $archived = $this->product('Archived Product', 'archived-product', ProductStatus::Archived);
        $scheduled = $this->product('Scheduled Product', 'scheduled-product', ProductStatus::Published, now()->addDay());
        $inactiveCategory = ProductCategory::factory()->create(['is_active' => false]);
        $inactive = Product::factory()->for($inactiveCategory, 'category')->published()->withStock(5)->create([
            'name' => 'Inactive Category Product',
            'slug' => 'inactive-category-product',
        ]);

        Livewire::test(CatalogBrowser::class)
            ->assertSee($visible->name)
            ->assertDontSee($draft->name)
            ->assertDontSee($archived->name)
            ->assertDontSee($scheduled->name)
            ->assertDontSee($inactive->name);

        foreach ([$draft, $archived, $scheduled, $inactive] as $hidden) {
            $this->get(route('commerce.storefront.products.show', ['product' => $hidden->slug]))->assertNotFound();
        }
    }

    public function test_livewire_catalog_filters_searches_and_sorts_using_effective_prices(): void
    {
        $audio = ProductCategory::factory()->create(['name' => 'Audio', 'slug' => 'audio', 'sort_order' => 10]);
        $computing = ProductCategory::factory()->create(['name' => 'Computing', 'slug' => 'computing', 'sort_order' => 20]);
        $headphones = Product::factory()->for($audio, 'category')->published()->onSale(15_900_00)->withStock(8)->create([
            'name' => 'Arc ANC Headphones',
            'slug' => 'arc-anc-headphones',
            'price_minor' => 18_500_00,
        ]);
        $speaker = Product::factory()->for($audio, 'category')->published()->withStock(0)->create([
            'name' => 'Echo Mini Speaker',
            'slug' => 'echo-mini-speaker',
            'price_minor' => 12_900_00,
        ]);
        $laptop = Product::factory()->for($computing, 'category')->published()->withStock(4)->create([
            'name' => 'StudioBook Pro 16',
            'slug' => 'studiobook-pro-16',
            'price_minor' => 189_500_00,
        ]);
        $lowerBoundary = Product::factory()->for($audio, 'category')->published()->withStock(4)->create([
            'name' => 'Boundary Product 25K',
            'slug' => 'boundary-product-25k',
            'price_minor' => 25_000_00,
        ]);
        $upperBoundary = Product::factory()->for($computing, 'category')->published()->withStock(4)->create([
            'name' => 'Boundary Product 150K',
            'slug' => 'boundary-product-150k',
            'price_minor' => 150_000_00,
        ]);

        Livewire::test(CatalogBrowser::class)
            ->set('search', 'Arc ANC')
            ->assertSee($headphones->name)
            ->assertDontSee($speaker->name)
            ->call('clearFilters')
            ->set('category', 'audio')
            ->assertSee($headphones->name)
            ->assertSee($speaker->name)
            ->assertDontSee($laptop->name)
            ->call('clearFilters')
            ->set('availability', 'out-of-stock')
            ->assertSee($speaker->name)
            ->assertDontSee($headphones->name)
            ->call('clearFilters')
            ->set('availability', 'on-sale')
            ->assertSee($headphones->name)
            ->assertDontSee($speaker->name)
            ->call('clearFilters')
            ->set('priceRange', 'under-25000')
            ->assertSee($headphones->name)
            ->assertSee($speaker->name)
            ->assertDontSee($lowerBoundary->name)
            ->assertDontSee($laptop->name)
            ->set('sort', 'price-asc')
            ->assertSeeInOrder([$speaker->name, $headphones->name])
            ->set('priceRange', '25000-75000')
            ->assertSee($lowerBoundary->name)
            ->assertDontSee($headphones->name)
            ->set('priceRange', '75000-150000')
            ->assertSee($upperBoundary->name)
            ->assertDontSee($laptop->name)
            ->set('priceRange', 'above-150000')
            ->assertSee($laptop->name)
            ->assertDontSee($upperBoundary->name);
    }

    public function test_money_formatting_and_effective_price_query_contract_stay_integer_based(): void
    {
        $this->assertSame('KSh 12,345.67', MoneyFormatter::format(12_345_67));
        $this->assertSame('KES 12,345.67', MoneyFormatter::format(12_345_67, false));

        $regular = $this->publishedProduct('Regular Product', 'regular-product', 20_000_00, 5);
        $sale = Product::factory()->for($regular->category, 'category')->published()->onSale(15_000_00)->withStock(5)->create([
            'name' => 'Sale Product',
            'slug' => 'sale-product',
            'price_minor' => 30_000_00,
        ]);

        $this->assertSame(
            [$sale->getKey(), $regular->getKey()],
            Product::query()->visibleInStorefront()->orderByEffectivePrice()->pluck('id')->all(),
        );
        $this->assertSame(
            [$sale->getKey()],
            Product::query()->visibleInStorefront()->whereEffectivePriceBetween(null, 15_000_00)->pluck('id')->all(),
        );
    }

    private function publishedProduct(string $name, string $slug, int $priceMinor, int $stock): Product
    {
        $category = ProductCategory::factory()->create();

        return Product::factory()->for($category, 'category')->published()->withStock($stock)->create([
            'name' => $name,
            'slug' => $slug,
            'price_minor' => $priceMinor,
            'specifications' => ['General' => ['Condition' => 'New']],
        ]);
    }

    private function product(
        string $name,
        string $slug,
        ProductStatus $status,
        mixed $publishedAt = null,
    ): Product {
        $category = ProductCategory::factory()->create();

        return Product::factory()->for($category, 'category')->withStock(5)->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }
}
