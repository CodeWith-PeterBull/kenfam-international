<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCatalog;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCategoryManager;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Inventory\Livewire\Admin\InventoryManager;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\ScaledDecimal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the authorized Commerce catalog and inventory administration gate.
 */
final class CommerceAdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
        ]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_catalog_and_inventory_routes_require_their_specific_permissions(): void
    {
        $this->get(route('commerce.admin.catalog.index'))->assertRedirect(route('login'));
        $this->get(route('commerce.admin.inventory.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('commerce.admin.catalog.index'))->assertForbidden();
        $this->actingAs($user)->get(route('commerce.admin.inventory.index'))->assertForbidden();

        $user->givePermissionTo(CommercePermission::VIEW_PRODUCTS, CommercePermission::VIEW_INVENTORY);
        $this->actingAs($user)->get(route('commerce.admin.catalog.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.admin.product-catalog')
            ->assertSeeLivewire('commerce.admin.product-category-manager');
        $this->actingAs($user)->get(route('commerce.admin.inventory.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.admin.inventory-manager');
    }

    public function test_view_only_users_can_browse_but_cannot_mutate_catalog_or_stock(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(CommercePermission::VIEW_PRODUCTS, CommercePermission::VIEW_INVENTORY);
        $product = Product::factory()->withStock(5)->create();

        Livewire::actingAs($viewer)
            ->test(ProductCatalog::class)
            ->assertSee($product->name)
            ->call('openCreate')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(ProductCategoryManager::class)
            ->call('openCreate')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(InventoryManager::class)
            ->assertSee($product->sku)
            ->call('openAdjustment', $product->stock()->firstOrFail()->id)
            ->assertForbidden();
    }

    public function test_catalog_links_only_currently_visible_products_to_the_storefront(): void
    {
        $activeCategory = ProductCategory::factory()->create(['is_active' => true]);
        $inactiveCategory = ProductCategory::factory()->create(['is_active' => false]);
        $visible = Product::factory()->for($activeCategory, 'category')->published()->create([
            'name' => 'Visible Storefront Product',
            'slug' => 'visible-storefront-product',
        ]);
        $draft = Product::factory()->create([
            'name' => 'Draft Storefront Product',
            'slug' => 'draft-storefront-product',
            'status' => ProductStatus::Draft,
        ]);
        $archived = Product::factory()->create([
            'name' => 'Archived Storefront Product',
            'slug' => 'archived-storefront-product',
            'status' => ProductStatus::Archived,
        ]);
        $scheduled = Product::factory()->published()->create([
            'name' => 'Scheduled Storefront Product',
            'slug' => 'scheduled-storefront-product',
            'published_at' => now()->addDay(),
        ]);
        $inactive = Product::factory()->for($inactiveCategory, 'category')->published()->create([
            'name' => 'Inactive Category Product',
            'slug' => 'inactive-category-product',
        ]);

        $this->assertTrue($visible->load('category')->isVisibleInStorefront());
        $this->assertFalse($draft->load('category')->isVisibleInStorefront());
        $this->assertFalse($archived->load('category')->isVisibleInStorefront());
        $this->assertFalse($scheduled->load('category')->isVisibleInStorefront());
        $this->assertFalse($inactive->load('category')->isVisibleInStorefront());

        $html = Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->html();
        $visibleUrl = route('commerce.storefront.products.show', ['product' => $visible->slug]);

        $this->assertStringContainsString('href="'.$visibleUrl.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString(route('commerce.storefront.products.show', ['product' => $draft->slug]), $html);
        $this->assertStringNotContainsString(route('commerce.storefront.products.show', ['product' => $archived->slug]), $html);
        $this->assertStringNotContainsString(route('commerce.storefront.products.show', ['product' => $scheduled->slug]), $html);
        $this->assertStringNotContainsString(route('commerce.storefront.products.show', ['product' => $inactive->slug]), $html);
        $this->assertSame(1, substr_count($html, 'data-storefront-product-link="available"'));
        $this->assertSame(4, substr_count($html, 'data-storefront-product-link="unavailable"'));
    }

    public function test_administrator_can_create_update_publish_and_image_a_product(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create(['name' => 'Computers']);

        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openCreate')
            ->set('form.name', 'Aureon Pro 14')
            ->set('form.sku', 'aur-pro-14')
            ->set('form.categoryId', (string) $category->id)
            ->set('form.price', '1250.50')
            ->set('form.costPrice', '900.00')
            ->set('form.shortDescription', 'A compact corporate workstation.')
            ->set('galleryUploads', [UploadedFile::fake()->image('front.webp', 600, 600)])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Product created as a draft.');

        $product = Product::query()->where('sku', 'AUR-PRO-14')->firstOrFail();
        $this->assertSame(125_050, $product->price_minor);
        $this->assertSame(90_000, $product->cost_price_minor);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(0, $product->stock()->firstOrFail()->on_hand);
        $this->assertTrue($product->hasMedia('product_gallery'));

        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openEdit', $product->id)
            ->set('form.name', 'Aureon Pro 14 Business')
            ->call('save')
            ->assertHasNoErrors()
            ->call('changeStatus', $product->id, ProductStatus::Published->value)
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertSame('Aureon Pro 14 Business', $product->name);
        $this->assertSame(ProductStatus::Published, $product->status);
        $this->assertNotNull($product->published_at);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.product.media_added']);

        $mediaId = $product->getFirstMedia('product_gallery')->id;
        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openEdit', $product->id)
            ->call('removeImage', $product->id, $mediaId)
            ->assertHasNoErrors();

        $this->assertFalse($product->refresh()->hasMedia('product_gallery'));
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.product.media_removed']);
    }

    public function test_administrator_can_manage_a_hierarchical_category_and_its_image(): void
    {
        Storage::fake('public');
        $parent = ProductCategory::factory()->create(['name' => 'Electronics']);

        Livewire::actingAs($this->administrator)
            ->test(ProductCategoryManager::class)
            ->call('openCreate')
            ->set('form.name', 'Laptops')
            ->set('form.parentId', (string) $parent->id)
            ->set('form.sortOrder', 10)
            ->set('categoryImageUpload', UploadedFile::fake()->image('laptops.png', 800, 500))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Product category created.');

        $category = ProductCategory::query()->where('slug', 'laptops')->firstOrFail();
        $this->assertTrue($category->is_active);
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertTrue($category->hasMedia('category_image'));

        Livewire::actingAs($this->administrator)
            ->test(ProductCategoryManager::class)
            ->call('toggleActive', $category->id)
            ->assertHasNoErrors();

        $this->assertFalse($category->refresh()->is_active);
    }

    public function test_administrator_can_post_a_stock_adjustment_and_filter_immutable_history(): void
    {
        $product = Product::factory()->withStock(12)->create([
            'name' => 'Aureon Dock',
            'sku' => 'AUR-DOCK',
        ]);
        $stock = $product->stock()->firstOrFail();

        Livewire::actingAs($this->administrator)
            ->test(InventoryManager::class)
            ->assertSee('Aureon Dock')
            ->call('openAdjustment', $stock->id)
            ->set('form.direction', 'decrease')
            ->set('form.quantity', 3)
            ->set('form.note', 'Damaged during receiving inspection')
            ->call('adjust')
            ->assertHasNoErrors()
            ->assertSee('Stock adjustment posted to the immutable ledger.')
            ->set('movementTypeFilter', 'adjustment_out')
            ->assertSee('Damaged during receiving inspection');

        $movement = StockMovement::query()->sole();
        $this->assertSame(-3, $movement->quantity_delta);
        $this->assertSame(12, $movement->balance_before);
        $this->assertSame(9, $movement->balance_after);
        $this->assertSame(9, $stock->refresh()->on_hand);
    }

    public function test_scaled_decimal_conversion_is_exact_and_rejects_excess_precision(): void
    {
        $this->assertSame(125_050, ScaledDecimal::parseUnsigned('1250.50', 2));
        $this->assertSame('1250.50', ScaledDecimal::formatUnsigned(125_050, 2));

        $this->expectException(\InvalidArgumentException::class);
        ScaledDecimal::parseUnsigned('12.345', 2);
    }
}
