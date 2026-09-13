<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Database\Seeders\CommerceAccessDemoSeeder;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\CommerceRole;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies the optional module fixture and its cross-table invariants.
 */
final class CommerceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_populates_every_commerce_table_and_is_idempotent(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 0);

        $this->seed(CommerceDemoSeeder::class);
        $this->assertDemoCounts();
        $this->assertDemoInvariants();

        $this->seed(CommerceDemoSeeder::class);
        $this->assertDemoCounts();
        $this->assertDemoInvariants();
    }

    private function assertDemoCounts(): void
    {
        $this->assertSame(5, User::query()->count());
        $this->assertSame(5, Role::query()->count());
        $this->assertSame(6, ProductCategory::query()->count());
        $this->assertSame(10, Product::query()->count());
        $this->assertSame(10, Stock::query()->count());
        $this->assertSame(3, Customer::query()->count());
        $this->assertSame(2, Register::query()->count());
        $this->assertSame(1, TillSession::query()->count());
        $this->assertSame(2, Order::query()->count());
        $this->assertSame(3, OrderItem::query()->count());
        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(13, StockMovement::query()->count());
        $this->assertSame(18, Media::query()->count());
    }

    private function assertDemoInvariants(): void
    {
        Product::query()->with(['stock', 'stockMovements', 'media'])->each(function (Product $product): void {
            $this->assertNotNull($product->stock);
            $this->assertTrue($product->isInStock());
            $this->assertNotEmpty($product->getFirstMediaUrl('product_gallery'));
            $this->assertSame(
                $product->stock->on_hand,
                (int) $product->stockMovements->sum('quantity_delta'),
                "Stock ledger did not reconcile for {$product->sku}.",
            );
        });

        ProductCategory::query()->with('media')->each(function (ProductCategory $category): void {
            $this->assertNotEmpty($category->getFirstMediaUrl('category_image'));
        });

        $webOrder = Order::query()->forChannel(OrderChannel::Web)->firstOrFail();
        $posOrder = Order::query()->forChannel(OrderChannel::PointOfSale)->with('payments')->firstOrFail();
        $till = TillSession::query()->firstOrFail();
        $cashier = User::query()->where('email', CommerceAccessDemoSeeder::CASHIER_EMAIL)->firstOrFail();
        $administrator = User::query()->where('email', 'admin@aureon.test')->firstOrFail();
        $cashierRole = Role::findByName(CommerceRole::POS_CASHIER, 'web');

        $this->assertNotNull($webOrder->stock_committed_at);
        $this->assertTrue($posOrder->isFullyPaid());
        $this->assertCount(2, $posOrder->payments);
        $this->assertSame($cashier->id, $posOrder->cashier_id);
        $this->assertSame($cashier->id, $till->opened_by);
        $this->assertFalse($till->isOpen());
        $this->assertSame(0, $till->variance_minor);
        $this->assertSame($till->expected_cash_minor, $till->counted_cash_minor);
        $this->assertSame(UserType::Viewer, $cashier->user_type);
        $this->assertTrue($cashier->is_active);
        $this->assertTrue($cashier->hasRole(UserType::Viewer->value));
        $this->assertTrue($cashier->hasRole(CommerceRole::POS_CASHIER));
        $this->assertTrue($cashier->can(CommercePermission::ACCESS_POS));
        $this->assertFalse($cashier->can(CommercePermission::MANAGE_TILLS));
        $this->assertEqualsCanonicalizing(
            [CommercePermission::ACCESS_POS],
            $cashierRole->permissions->pluck('name')->all(),
        );
        $this->assertTrue($administrator->can(CommercePermission::ACCESS_POS));
        $this->assertTrue($administrator->can(CommercePermission::MANAGE_TILLS));
    }
}
