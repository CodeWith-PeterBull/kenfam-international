<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Livewire\Admin\BarcodeLabelSheet;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCatalog;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\PointOfSale\Livewire\Terminal;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Barcode assignment, manufacturer barcode, POS scan matching, and label printing.
 */
final class CommerceBarcodeTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_creating_a_product_without_a_barcode_auto_generates_an_internal_one(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openCreate')
            ->set('form.name', 'Auto Barcode Widget')
            ->set('form.sku', 'auto-bc-1')
            ->set('form.price', '250.00')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'AUTO-BC-1')->firstOrFail();
        $prefix = strtoupper((string) config('commerce.barcode.internal_prefix', 'MM'));

        $this->assertNotNull($product->barcode);
        $this->assertStringStartsWith($prefix, (string) $product->barcode);
        $this->assertStringContainsString((string) $product->id, (string) $product->barcode);
    }

    public function test_supplied_internal_and_manufacturer_barcodes_are_kept_and_uppercased(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openCreate')
            ->set('form.name', 'Manual Barcode Widget')
            ->set('form.sku', 'man-bc-1')
            ->set('form.barcode', 'store-9001')
            ->set('form.manufacturerBarcode', '6161101234567')
            ->set('form.price', '250.00')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'MAN-BC-1')->firstOrFail();

        $this->assertSame('STORE-9001', $product->barcode);
        $this->assertSame('6161101234567', $product->manufacturer_barcode);
    }

    public function test_manufacturer_barcode_must_be_unique(): void
    {
        Product::factory()->create(['manufacturer_barcode' => '6161109999999']);

        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openCreate')
            ->set('form.name', 'Duplicate Manufacturer Widget')
            ->set('form.sku', 'dup-man-1')
            ->set('form.manufacturerBarcode', '6161109999999')
            ->set('form.price', '100.00')
            ->call('save')
            ->assertHasErrors(['form.manufacturerBarcode']);
    }

    public function test_generate_internal_barcode_action_fills_the_form(): void
    {
        $prefix = strtoupper((string) config('commerce.barcode.internal_prefix', 'MM'));

        Livewire::actingAs($this->administrator)
            ->test(ProductCatalog::class)
            ->call('openCreate')
            ->call('generateInternalBarcode')
            ->assertSet('form.barcode', fn (string $value): bool => str_starts_with($value, $prefix));
    }

    public function test_pos_scan_matches_internal_manufacturer_and_sku(): void
    {
        $cashier = User::factory()->create();
        $cashier->givePermissionTo(CommercePermission::ACCESS_POS);
        app(TillService::class)->open(Register::factory()->create(), $cashier, 10_000);

        $product = Product::factory()->published()->withStock(50)->create([
            'sku' => 'SCAN-SKU-1',
            'barcode' => 'MM0000009001',
            'manufacturer_barcode' => '6161105551234',
            'price_minor' => 15000,
            'sale_price_minor' => null,
        ]);

        foreach (['MM0000009001', '6161105551234', 'SCAN-SKU-1'] as $scanned) {
            Livewire::actingAs($cashier)
                ->test(Terminal::class)
                ->set('search', $scanned)
                ->call('lookup')
                ->assertSet('cart', [$product->id => 1])
                ->assertHasNoErrors();
        }
    }

    public function test_barcode_print_page_enforces_product_permission(): void
    {
        $this->get(route('commerce.admin.catalog.barcodes'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('commerce.admin.catalog.barcodes'))->assertForbidden();

        $user->givePermissionTo(CommercePermission::VIEW_PRODUCTS);
        $this->actingAs($user)->get(route('commerce.admin.catalog.barcodes'))->assertOk();
    }

    public function test_label_sheet_component_generates_a_pdf_download(): void
    {
        $product = Product::factory()->published()->create([
            'sku' => 'LABEL-SKU-1',
            'barcode' => 'MM0000008001',
            'price_minor' => 12000,
        ]);

        Livewire::actingAs($this->administrator)
            ->test(BarcodeLabelSheet::class)
            ->call('addProduct', $product->id)
            ->assertCount('items', 1)
            ->call('generate')
            ->assertHasNoErrors()
            ->assertFileDownloaded();
    }

    public function test_label_sheet_rejects_a_product_without_any_barcode(): void
    {
        $product = Product::factory()->create([
            'sku' => 'NOBC-SKU-1',
            'barcode' => null,
            'manufacturer_barcode' => null,
        ]);

        Livewire::actingAs($this->administrator)
            ->test(BarcodeLabelSheet::class)
            ->call('addProduct', $product->id)
            ->call('generate')
            ->assertHasErrors('sheet');
    }
}
