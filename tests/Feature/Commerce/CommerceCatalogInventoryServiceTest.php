<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Services\ProductService;
use App\Modules\Commerce\Customers\Services\CustomerService;
use App\Modules\Commerce\Exceptions\CatalogException;
use App\Modules\Commerce\Exceptions\InsufficientStockException;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Verifies catalog, customer, and inventory service-owned mutations.
 */
final class CommerceCatalogInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_creates_a_draft_with_stock_and_controls_publication(): void
    {
        $actor = User::factory()->create();
        $service = app(ProductService::class);
        $category = $service->createCategory(['name' => 'Laptops', 'is_active' => true], $actor);
        $product = $service->createProduct([
            'category_id' => $category->id,
            'name' => 'Aureon Pro 14',
            'slug' => 'Aureon Pro 14',
            'sku' => ' aur-pro-14 ',
            'price_minor' => 125_000,
        ], $actor);

        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame('AUR-PRO-14', $product->sku);
        $this->assertSame('aureon-pro-14', $product->slug);
        $this->assertSame(0, $product->stock->on_hand);
        $this->assertSame($actor->id, $product->created_by);

        $published = $service->transition($product, ProductStatus::Published, $actor);
        $this->assertSame(ProductStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.product.created']);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.product.status_changed']);
    }

    public function test_catalog_service_rejects_invalid_sale_windows_and_category_cycles(): void
    {
        $actor = User::factory()->create();
        $service = app(ProductService::class);
        $parent = $service->createCategory(['name' => 'Electronics'], $actor);
        $child = $service->createCategory(['name' => 'Computers', 'parent_id' => $parent->id], $actor);

        try {
            $service->updateCategory($parent, ['parent_id' => $child->id], $actor);
            $this->fail('A category cycle was accepted.');
        } catch (CatalogException $exception) {
            $this->assertStringContainsString('cycle', $exception->getMessage());
        }

        $product = $service->createProduct([
            'category_id' => $child->id,
            'name' => 'Test product',
            'slug' => 'test-product',
            'sku' => 'TEST-1',
            'price_minor' => 10_000,
        ], $actor);

        $this->expectException(CatalogException::class);
        $service->updateProduct($product, [
            'sale_price_minor' => 9_000,
            'sale_starts_at' => now()->addDay(),
            'sale_ends_at' => now(),
        ], $actor);
    }

    public function test_customer_service_normalizes_and_audits_reusable_records(): void
    {
        $actor = User::factory()->create();
        $service = app(CustomerService::class);
        $customer = $service->create([
            'first_name' => '  Jane ',
            'last_name' => ' Doe ',
            'email' => ' JANE@EXAMPLE.TEST ',
            'country_code' => 'ke',
        ], $actor);

        $this->assertSame('Jane Doe', $customer->display_name);
        $this->assertSame('jane@example.test', $customer->email);
        $this->assertSame('KE', $customer->country_code);

        $updated = $service->update($customer, ['company' => 'Aureon Labs'], $actor);
        $this->assertSame('Aureon Labs', $updated->company);
        $service->delete($updated, $actor);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.customer.created']);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.customer.updated']);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.customer.archived']);
    }

    public function test_manual_stock_adjustments_reconcile_projection_and_ledger(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->published()->withStock(5)->create();
        $service = app(InventoryService::class);

        $increase = $service->adjust($product, 7, 'Opening warehouse count', $actor);
        $decrease = $service->adjust($product, -3, 'Damaged stock write-off', $actor);

        $this->assertSame(5, $increase->balance_before);
        $this->assertSame(12, $increase->balance_after);
        $this->assertSame(StockMovementType::AdjustmentIn, $increase->type);
        $this->assertSame(12, $decrease->balance_before);
        $this->assertSame(9, $decrease->balance_after);
        $this->assertSame(StockMovementType::AdjustmentOut, $decrease->type);
        $this->assertSame(9, $product->stock()->firstOrFail()->on_hand);
        $this->assertSame(2, StockMovement::query()->whereBelongsTo($product)->count());
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.stock.adjusted']);
    }

    public function test_insufficient_adjustment_rolls_back_projection_and_movement(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->published()->withStock(2)->create();

        try {
            app(InventoryService::class)->adjust($product, -3, 'Invalid write-off', $actor);
            $this->fail('A negative stock projection was accepted.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame($product->ulid, $exception->productUlid);
            $this->assertSame(3, $exception->requested);
            $this->assertSame(2, $exception->available);
        }

        $this->assertSame(2, $product->stock()->firstOrFail()->on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseMissing('system_activities', ['activity_type' => 'commerce.stock.adjusted']);
    }

    public function test_stock_movements_are_append_only(): void
    {
        $movement = StockMovement::factory()->create();
        $movement->forceFill(['note' => 'Rewritten history']);

        try {
            $movement->save();
            $this->fail('A stock movement update was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $movement->fresh()->delete();
    }
}
