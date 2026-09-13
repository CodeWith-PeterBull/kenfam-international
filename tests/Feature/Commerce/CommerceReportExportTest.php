<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\ReportOrientation;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCatalog;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Livewire\Admin\InventoryManager;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Livewire\Admin\OrderManager;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Reporting\Filters\OrderReportFilters;
use App\Modules\Commerce\Reporting\Filters\ProductReportFilters;
use App\Modules\Commerce\Reporting\Filters\StockLevelReportFilters;
use App\Modules\Commerce\Reporting\Filters\StockMovementReportFilters;
use App\Modules\Commerce\Reporting\Reports\OrderRegisterReport;
use App\Modules\Commerce\Reporting\Reports\ProductCatalogReport;
use App\Modules\Commerce\Reporting\Reports\StockLevelReport;
use App\Modules\Commerce\Reporting\Reports\StockMovementReport;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtered PDF register exports for products, orders, and inventory.
 */
final class CommerceReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_product_catalogue_report_renders_selling_prices_and_excludes_cost(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Audio']);
        Product::factory()->published()->withStock(12)->create([
            'name' => 'Arc Headphones',
            'sku' => 'ARC-1',
            'category_id' => $category->id,
            'price_minor' => 500000,
            'sale_price_minor' => null,
            'cost_price_minor' => 777777,
            'track_stock' => true,
        ]);

        $report = app(ProductCatalogReport::class);
        $rows = $report->rows(new ProductReportFilters);

        $this->assertCount(1, $rows);
        $this->assertSame('ARC-1', $rows[0]->sku);
        $this->assertStringContainsString('5,000.00', $rows[0]->sellingPrice);
        $this->assertSame('12', $rows[0]->stock);

        // Privacy: the supplier cost figure must never reach a display row.
        foreach ((array) $rows[0] as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('7,777.77', $value);
            }
        }

        $rendered = $report->render(new ProductReportFilters, $this->administrator);
        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertSame('product-catalogue.pdf', $rendered->filename);
        $this->assertGreaterThanOrEqual(1, $rendered->pageCount);
    }

    public function test_product_report_applies_status_category_and_search_filters(): void
    {
        $audio = ProductCategory::factory()->create(['name' => 'Audio']);
        $mobile = ProductCategory::factory()->create(['name' => 'Mobile']);
        Product::factory()->published()->create(['name' => 'Alpha Speaker', 'sku' => 'A1', 'category_id' => $audio->id]);
        Product::factory()->create(['name' => 'Beta Phone', 'sku' => 'B1', 'category_id' => $mobile->id, 'status' => ProductStatus::Draft->value]);

        $report = app(ProductCatalogReport::class);

        $published = $report->rows(new ProductReportFilters(status: ProductStatus::Published));
        $this->assertCount(1, $published);
        $this->assertSame('A1', $published[0]->sku);

        $inMobile = $report->rows(new ProductReportFilters(categoryId: $mobile->id));
        $this->assertCount(1, $inMobile);
        $this->assertSame('B1', $inMobile[0]->sku);

        $searched = $report->rows(new ProductReportFilters(search: 'Alpha'));
        $this->assertCount(1, $searched);

        $labels = $report->filterLabels(new ProductReportFilters(search: 'Alpha', status: ProductStatus::Published, categoryId: $audio->id));
        $this->assertContains('Search: Alpha', $labels);
        $this->assertContains('Status: Published', $labels);
        $this->assertContains('Category: Audio', $labels);
    }

    public function test_order_register_report_renders_and_filters(): void
    {
        Order::factory()->create([
            'order_number' => 'WEB-100', 'channel' => OrderChannel::Web, 'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid, 'total_minor' => 500000, 'paid_minor' => 500000,
            'customer_first_name' => 'Ada', 'customer_last_name' => 'Lovelace',
        ]);
        Order::factory()->create([
            'order_number' => 'POS-200', 'channel' => OrderChannel::PointOfSale, 'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Unpaid, 'total_minor' => 100000, 'paid_minor' => 0,
        ]);

        $report = app(OrderRegisterReport::class);

        $this->assertCount(2, $report->rows(new OrderReportFilters));

        $web = $report->rows(new OrderReportFilters(channel: OrderChannel::Web));
        $this->assertCount(1, $web);
        $this->assertSame('WEB-100', $web[0]->orderNumber);
        $this->assertStringContainsString('5,000.00', $web[0]->total);

        $completed = $report->rows(new OrderReportFilters(status: OrderStatus::Completed));
        $this->assertCount(1, $completed);

        $rendered = $report->render(new OrderReportFilters, $this->administrator);
        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertSame('order-register.pdf', $rendered->filename);
    }

    public function test_stock_level_report_renders_and_reflects_state_filter(): void
    {
        Product::factory()->withStock(50)->create(['name' => 'Healthy Item', 'sku' => 'H1', 'track_stock' => true]);
        Product::factory()->withStock(0)->create(['name' => 'Sold Out Item', 'sku' => 'S1', 'track_stock' => true]);

        $report = app(StockLevelReport::class);

        $this->assertCount(2, $report->rows(new StockLevelReportFilters));

        $out = $report->rows(new StockLevelReportFilters(stockState: 'out'));
        $this->assertCount(1, $out);
        $this->assertSame('S1', $out[0]->sku);
        $this->assertSame('Out of stock', $out[0]->state);

        $rendered = $report->render(new StockLevelReportFilters, $this->administrator);
        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertSame('stock-levels.pdf', $rendered->filename);
    }

    public function test_stock_movement_report_renders_and_filters_by_type(): void
    {
        $product = Product::factory()->create(['name' => 'Widget', 'sku' => 'W1']);
        StockMovement::factory()->create(['product_id' => $product->id, 'type' => StockMovementType::Opening, 'quantity_delta' => 10, 'balance_before' => 0, 'balance_after' => 10]);
        StockMovement::factory()->create(['product_id' => $product->id, 'type' => StockMovementType::AdjustmentOut, 'quantity_delta' => -3, 'balance_before' => 10, 'balance_after' => 7]);

        $report = app(StockMovementReport::class);
        $this->assertCount(2, $report->rows(new StockMovementReportFilters));

        $out = $report->rows(new StockMovementReportFilters(movementType: StockMovementType::AdjustmentOut));
        $this->assertCount(1, $out);
        $this->assertSame('-3', $out[0]->quantityDelta);
        $this->assertSame(7, $out[0]->balanceAfter);

        $rendered = $report->render(new StockMovementReportFilters, $this->administrator);
        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertSame('stock-movements.pdf', $rendered->filename);
    }

    public function test_reports_render_with_no_matching_data(): void
    {
        $rendered = app(ProductCatalogReport::class)->render(new ProductReportFilters(search: 'nothing-matches'), $this->administrator);

        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertGreaterThanOrEqual(1, $rendered->pageCount);
        $this->assertSame([], app(ProductCatalogReport::class)->rows(new ProductReportFilters(search: 'nothing-matches')));
    }

    public function test_report_renders_both_orientations_and_paginates_long_data(): void
    {
        $category = ProductCategory::factory()->create();
        Product::factory()->count(120)->create(['category_id' => $category->id]);

        $report = app(ProductCatalogReport::class);
        $portrait = $report->render(new ProductReportFilters, $this->administrator, ReportOrientation::Portrait);
        $landscape = $report->render(new ProductReportFilters, $this->administrator, ReportOrientation::Landscape);

        $this->assertStringStartsWith('%PDF', $portrait->contents);
        $this->assertStringStartsWith('%PDF', $landscape->contents);
        $this->assertGreaterThan(1, $landscape->pageCount);
    }

    public function test_livewire_export_actions_stream_pdf_downloads(): void
    {
        Product::factory()->published()->withStock(5)->create();
        Order::factory()->create();
        $product = Product::factory()->create();
        StockMovement::factory()->create(['product_id' => $product->id]);

        Livewire::actingAs($this->administrator)->test(ProductCatalog::class)
            ->call('exportPdf')->assertFileDownloaded('product-catalogue.pdf');

        Livewire::actingAs($this->administrator)->test(OrderManager::class)
            ->call('exportPdf')->assertFileDownloaded('order-register.pdf');

        Livewire::actingAs($this->administrator)->test(InventoryManager::class)
            ->call('exportStockLevelsPdf')->assertFileDownloaded('stock-levels.pdf');

        Livewire::actingAs($this->administrator)->test(InventoryManager::class)
            ->call('exportMovementsPdf')->assertFileDownloaded('stock-movements.pdf');
    }

    public function test_export_is_gated_by_the_view_products_permission(): void
    {
        Product::factory()->published()->withStock(3)->create();
        $viewer = User::factory()->create();

        // The component boot() authorizes this policy on every request, and the
        // page hosting the export enforces the same permission at the route.
        $this->assertFalse($viewer->can('viewAny', Product::class));
        $this->actingAs($viewer)->get(route('commerce.admin.catalog.index'))->assertForbidden();

        $viewer->givePermissionTo(CommercePermission::VIEW_PRODUCTS);
        $viewer = $viewer->fresh();
        $this->assertTrue($viewer->can('viewAny', Product::class));
        $this->actingAs($viewer)->get(route('commerce.admin.catalog.index'))->assertOk();

        Livewire::actingAs($viewer)->test(ProductCatalog::class)
            ->call('exportPdf')->assertFileDownloaded('product-catalogue.pdf');
    }
}
