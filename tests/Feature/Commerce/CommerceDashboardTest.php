<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Reporting\Enums\CommerceDashboardRange;
use App\Modules\Commerce\Reporting\Services\CommerceDashboardService;
use App\Modules\Commerce\Support\CommercePermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Verifies Commerce dashboard metrics, permissions, deep links, and empty-safe UI.
 */
final class CommerceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->now = CarbonImmutable::parse('2026-07-19 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_route_requires_its_dedicated_permission(): void
    {
        $plainUser = User::factory()->create();
        $dashboardUser = User::factory()->create();
        $dashboardUser->givePermissionTo(CommercePermission::VIEW_DASHBOARD);

        $this->get(route('commerce.admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($plainUser)->get(route('commerce.admin.dashboard'))->assertForbidden();
        $response = $this->actingAs($dashboardUser)->get(route('commerce.admin.dashboard'))
            ->assertOk()
            ->assertSee('data-commerce-dashboard', false)
            ->assertSee('Commerce overview')
            ->assertDontSee(route('commerce.admin.orders.index'), false)
            ->assertDontSee(route('commerce.admin.inventory.index'), false);

        $this->assertSame(8, substr_count($response->getContent(), 'data-commerce-metric'));
    }

    public function test_system_administrator_retains_dashboard_access_when_seeded_permission_is_stale(): void
    {
        $role = Role::findByName(UserType::SystemAdministrator->value, 'web');
        $role->revokePermissionTo(CommercePermission::VIEW_DASHBOARD);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $administrator->assignRole($role);

        $this->assertFalse($role->fresh()->hasPermissionTo(CommercePermission::VIEW_DASHBOARD));
        $this->actingAs($administrator)
            ->get(route('commerce.admin.dashboard'))
            ->assertOk()
            ->assertSee('Commerce overview');
    }

    public function test_dashboard_validates_ranges_and_exposes_only_authorized_working_links(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo(
            CommercePermission::VIEW_DASHBOARD,
            CommercePermission::VIEW_ORDERS,
            CommercePermission::VIEW_INVENTORY,
            CommercePermission::VIEW_PRODUCTS,
            CommercePermission::MANAGE_CUSTOMERS,
            CommercePermission::MANAGE_DEMO_DATA,
            CommercePermission::ACCESS_POS,
            CommercePermission::MANAGE_TILLS,
        );
        $this->stock(onHand: 2, threshold: 5, tracked: true);

        $this->actingAs($operator)
            ->get(route('commerce.admin.dashboard', ['range' => 7]))
            ->assertOk()
            ->assertSee('7 days')
            ->assertSee(route('commerce.admin.orders.index'), false)
            ->assertSee(route('commerce.admin.inventory.index'), false)
            ->assertSee(route('commerce.admin.inventory.index', ['stock-state' => 'low']), false)
            ->assertSee(route('commerce.admin.catalog.index'), false)
            ->assertSee(route('commerce.admin.customers.index'), false)
            ->assertSee(route('commerce.admin.demo-data.index'), false)
            ->assertSee(route('commerce.pos.terminal'), false)
            ->assertSee(route('commerce.pos.admin.tills.index'), false);

        $this->actingAs($operator)
            ->from(route('commerce.admin.dashboard'))
            ->get(route('commerce.admin.dashboard', ['range' => 13]))
            ->assertRedirect(route('commerce.admin.dashboard'))
            ->assertSessionHasErrors('range');
    }

    public function test_dashboard_snapshot_uses_exact_period_channel_stock_and_till_semantics(): void
    {
        $web = $this->order([
            'channel' => OrderChannel::Web,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Partial,
            'total_minor' => 10_000,
        ]);
        $pos = $this->order([
            'channel' => OrderChannel::PointOfSale,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'total_minor' => 20_000,
            'paid_minor' => 20_000,
            'completed_at' => $this->now,
        ]);
        $this->order([
            'channel' => OrderChannel::Web,
            'status' => OrderStatus::Cancelled,
            'total_minor' => 30_000,
            'cancelled_at' => $this->now,
        ]);
        $outside = $this->order([
            'channel' => OrderChannel::Web,
            'status' => OrderStatus::Completed,
            'total_minor' => 40_000,
            'placed_at' => $this->now->subDays(31),
        ]);
        Order::factory()->held()->create(['customer_id' => null]);

        $this->payment($web, 5_000, PaymentMethod::MobileMoney, $this->now);
        $this->payment($pos, 20_000, PaymentMethod::Cash, $this->now);
        $this->payment($outside, 40_000, PaymentMethod::Card, $this->now->subDays(31));
        Payment::factory()->for($web)->create([
            'status' => PaymentStatus::Pending,
            'amount_minor' => 99_999,
            'paid_at' => null,
        ]);

        $this->stock(onHand: 10, threshold: 5, tracked: true);
        $this->stock(onHand: 3, threshold: 5, tracked: true);
        $this->stock(onHand: 0, threshold: 5, tracked: true);
        $this->stock(onHand: 0, threshold: 5, tracked: false);

        Customer::factory()->create();
        Customer::factory()->create()->delete();
        TillSession::factory()->create([
            'expected_cash_minor' => 32_000,
            'opened_at' => $this->now->subHours(2),
        ]);
        TillSession::factory()->closed()->create();

        $snapshot = app(CommerceDashboardService::class)->snapshot(
            CommerceDashboardRange::ThirtyDays,
            $this->now,
        );

        $this->assertSame(25_000, $snapshot->paymentsCollectedMinor);
        $this->assertSame(30_000, $snapshot->orderValueMinor);
        $this->assertSame(3, $snapshot->ordersReceived);
        $this->assertSame(15_000, $snapshot->averageOrderValueMinor);
        $this->assertSame(1, $snapshot->actionableOrders);
        $this->assertSame(1, $snapshot->lowStockCount);
        $this->assertSame(1, $snapshot->outOfStockCount);
        $this->assertSame(1, $snapshot->activeTillCount);
        $this->assertSame(1, $snapshot->activeCustomerCount);
        $this->assertCount(30, $snapshot->salesSeries);
        $this->assertSame(5_000, $snapshot->salesSeries[29]->webMinor);
        $this->assertSame(20_000, $snapshot->salesSeries[29]->pointOfSaleMinor);
        $this->assertSame(2, $snapshot->channelSummaries[0]->orderCount);
        $this->assertSame(10_000, $snapshot->channelSummaries[0]->orderValueMinor);
        $this->assertSame(1, $snapshot->channelSummaries[1]->orderCount);
        $this->assertSame(20_000, $snapshot->channelSummaries[1]->orderValueMinor);
        $this->assertCount(2, $snapshot->paymentMethodSummaries);
        $this->assertCount(3, $snapshot->recentOrders);
        $this->assertCount(2, $snapshot->stockAlerts);
        $this->assertSame('out', $snapshot->stockAlerts[0]->state);
        $this->assertCount(1, $snapshot->activeTills);
        $this->assertSame(32_000, $snapshot->activeTills[0]->expectedCashMinor);
    }

    public function test_dashboard_renders_stable_empty_states_and_sidebar_honors_module_disablement(): void
    {
        $administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $administrator->assignRole(UserType::SystemAdministrator->value);

        $response = $this->actingAs($administrator)
            ->get(route('commerce.admin.dashboard'))
            ->assertOk()
            ->assertSee('No orders were placed in this period.')
            ->assertSee('Tracked stock is healthy.')
            ->assertSee('No cashier till is currently open.')
            ->assertSee('No completed payments in this period.');

        $this->assertSame(8, substr_count($response->getContent(), 'data-commerce-metric'));

        config()->set('commerce.enabled', false);
        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Commerce overview');
    }

    /**
     * Create a numbered order in the active reporting period.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes): Order
    {
        return Order::factory()->create([
            'customer_id' => null,
            'placed_at' => $this->now,
            ...$attributes,
        ]);
    }

    /**
     * Create one completed payment at an explicit collection time.
     */
    private function payment(Order $order, int $amountMinor, PaymentMethod $method, CarbonImmutable $paidAt): Payment
    {
        return Payment::factory()->for($order)->create([
            'method' => $method,
            'status' => PaymentStatus::Completed,
            'amount_minor' => $amountMinor,
            'tendered_minor' => $amountMinor,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * Create one stock projection with explicit tracking and warning semantics.
     */
    private function stock(int $onHand, int $threshold, bool $tracked): Stock
    {
        $product = Product::factory()->create(['track_stock' => $tracked]);

        return Stock::factory()->for($product)->create([
            'on_hand' => $onHand,
            'low_stock_threshold' => $threshold,
        ]);
    }
}
