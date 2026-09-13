<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Livewire\CashierSalesHistory;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies cashier sales visibility, filtering, placement, and public shop links.
 */
final class CommerceCashierSalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_component_rejects_users_without_pos_access(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CashierSalesHistory::class)
            ->assertForbidden();
    }

    public function test_cashier_can_switch_between_strictly_scoped_active_and_historical_sales(): void
    {
        $cashier = $this->userWith(CommercePermission::ACCESS_POS);
        $otherCashier = $this->userWith(CommercePermission::ACCESS_POS);

        $oldTill = TillSession::factory()->closed()->create([
            'register_id' => Register::factory()->create(['name' => 'Former counter'])->id,
            'opened_by' => $cashier->id,
            'closed_by' => $cashier->id,
            'opened_at' => now()->subDays(46),
            'closed_at' => now()->subDays(45),
        ]);
        $activeTill = app(TillService::class)->open(
            Register::factory()->create(['name' => 'Cashier counter', 'code' => 'CASH-01']),
            $cashier,
            10_000,
        );
        $otherTill = app(TillService::class)->open(Register::factory()->create(), $otherCashier, 10_000);

        $historicalSale = $this->completedSale($cashier, $oldTill, 8_000, now()->subDays(45), 'POS-HISTORY-OLD');
        $activeSale = $this->completedSale($cashier, $activeTill, 12_000, now(), 'POS-HISTORY-ACTIVE');
        $otherCashierSale = $this->completedSale($otherCashier, $otherTill, 90_000, now(), 'POS-HISTORY-OTHER');

        $webOrder = Order::factory()->create([
            'order_number' => 'WEB-HISTORY-EXCLUDED',
            'channel' => OrderChannel::Web,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'cashier_id' => $cashier->id,
            'placed_at' => now(),
            'completed_at' => now(),
        ]);
        $heldOrder = Order::factory()->held()->create([
            'channel' => OrderChannel::PointOfSale,
            'cashier_id' => $cashier->id,
            'register_id' => $activeTill->register_id,
            'till_session_id' => $activeTill->id,
        ]);

        $component = Livewire::actingAs($cashier)
            ->test(CashierSalesHistory::class, ['surface' => 'dashboard'])
            ->assertSet('tab', 'active')
            ->assertSee($activeSale->order_number)
            ->assertSee('KSh 120.00')
            ->assertDontSee($historicalSale->order_number)
            ->assertDontSee($otherCashierSale->order_number)
            ->assertDontSee($webOrder->order_number)
            ->assertDontSee((string) $heldOrder->ulid)
            ->assertSee(route('commerce.pos.receipts.show', $activeSale), escape: false)
            ->call('showAllSales')
            ->assertSet('tab', 'history')
            ->assertSee($activeSale->order_number)
            ->assertSee($historicalSale->order_number)
            ->assertSee('KSh 200.00')
            ->assertSee('KSh 100.00')
            ->assertDontSee($otherCashierSale->order_number)
            ->assertDontSee($webOrder->order_number)
            ->set('historyRange', '30_days')
            ->assertSet('historyRange', '30_days')
            ->assertSee($activeSale->order_number)
            ->assertDontSee($historicalSale->order_number);

        $component
            ->set('historyRange', 'untrusted-range')
            ->assertSet('historyRange', 'all')
            ->assertSee($historicalSale->order_number);
    }

    public function test_cashier_without_an_open_till_can_still_review_prior_sessions(): void
    {
        $cashier = $this->userWith(CommercePermission::ACCESS_POS);
        $closedTill = TillSession::factory()->closed()->create([
            'opened_by' => $cashier->id,
            'closed_by' => $cashier->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subHours(20),
        ]);
        $sale = $this->completedSale($cashier, $closedTill, 25_000, now()->subHours(21), 'POS-CLOSED-ONLY');

        Livewire::actingAs($cashier)
            ->test(CashierSalesHistory::class)
            ->assertSee('No active till')
            ->assertDontSee($sale->order_number)
            ->call('showAllSales')
            ->assertSee($sale->order_number);
    }

    public function test_cashier_history_uses_the_bounded_named_paginator(): void
    {
        config(['commerce.pos.sales_history.per_page' => 5]);

        $cashier = $this->userWith(CommercePermission::ACCESS_POS);
        $till = app(TillService::class)->open(Register::factory()->create(), $cashier, 10_000);

        foreach (range(1, 6) as $sequence) {
            $this->completedSale(
                $cashier,
                $till,
                1_000 * $sequence,
                now()->subMinutes($sequence),
                "POS-PAGE-{$sequence}",
            );
        }

        Livewire::actingAs($cashier)
            ->test(CashierSalesHistory::class)
            ->assertSee('POS-PAGE-1')
            ->assertDontSee('POS-PAGE-6')
            ->call('setPage', 2, 'cashierSalesPage')
            ->assertSee('POS-PAGE-6')
            ->assertDontSee('POS-PAGE-1');
    }

    public function test_terminal_and_role_dashboard_mounts_follow_independent_configuration(): void
    {
        $cashier = User::factory()->create(['user_type' => UserType::Viewer]);
        $cashier->assignRole(UserType::Viewer->value);
        $cashier->givePermissionTo(CommercePermission::ACCESS_POS);
        app(TillService::class)->open(Register::factory()->create(), $cashier, 10_000);

        config([
            'commerce.pos.sales_history.terminal_enabled' => true,
            'commerce.pos.sales_history.dashboard_enabled' => true,
        ]);

        $terminal = $this->actingAs($cashier)
            ->get(route('commerce.pos.terminal'))
            ->assertOk()
            ->assertSeeLivewire('commerce.pos.cashier-sales-history')
            ->assertSee(route('commerce.storefront.catalog.index'), escape: false)
            ->assertSee('Open shop frontend in a new tab');

        $dashboard = $this->actingAs($cashier)
            ->get(route('viewer.dashboard'))
            ->assertOk()
            ->assertSeeLivewire('commerce.pos.cashier-sales-history')
            ->assertSee('Shop frontend')
            ->assertSee(route('commerce.storefront.catalog.index'), escape: false);

        $this->assertStringContainsString('target="_blank"', $terminal->getContent());
        $this->assertStringContainsString('target="_blank"', $dashboard->getContent());

        config([
            'commerce.pos.sales_history.terminal_enabled' => false,
            'commerce.pos.sales_history.dashboard_enabled' => false,
        ]);

        $this->actingAs($cashier)
            ->get(route('commerce.pos.terminal'))
            ->assertOk()
            ->assertDontSeeLivewire('commerce.pos.cashier-sales-history')
            ->assertSee('Open shop frontend in a new tab');

        $this->actingAs($cashier)
            ->get(route('viewer.dashboard'))
            ->assertOk()
            ->assertDontSeeLivewire('commerce.pos.cashier-sales-history')
            ->assertSee('Shop frontend');
    }

    public function test_commerce_overview_mounts_operator_history_and_shop_quick_action(): void
    {
        $operator = $this->userWith(
            CommercePermission::VIEW_DASHBOARD,
            CommercePermission::ACCESS_POS,
        );

        $response = $this->actingAs($operator)
            ->get(route('commerce.admin.dashboard'))
            ->assertOk()
            ->assertSeeLivewire('commerce.pos.cashier-sales-history')
            ->assertSee('Shop frontend')
            ->assertSee(route('commerce.storefront.catalog.index'), escape: false);

        $this->assertStringContainsString('rel="noopener noreferrer"', $response->getContent());
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function completedSale(
        User $cashier,
        TillSession $till,
        int $totalMinor,
        DateTimeInterface $placedAt,
        string $orderNumber,
    ): Order {
        $order = Order::factory()->create([
            'order_number' => $orderNumber,
            'channel' => OrderChannel::PointOfSale,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'fulfillment_type' => FulfillmentType::Counter,
            'preferred_payment_method' => PaymentMethod::Cash,
            'customer_id' => null,
            'register_id' => $till->register_id,
            'till_session_id' => $till->id,
            'cashier_id' => $cashier->id,
            'created_by' => $cashier->id,
            'subtotal_minor' => $totalMinor,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'paid_minor' => $totalMinor,
            'stock_committed_at' => $placedAt,
            'placed_at' => $placedAt,
            'completed_at' => $placedAt,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price_minor' => $totalMinor,
            'line_subtotal_minor' => $totalMinor,
            'tax_minor' => 0,
            'line_total_minor' => $totalMinor,
        ]);
        Payment::factory()->completed()->create([
            'order_id' => $order->id,
            'till_session_id' => $till->id,
            'recorded_by' => $cashier->id,
            'method' => PaymentMethod::Cash,
            'amount_minor' => $totalMinor,
            'tendered_minor' => $totalMinor,
            'change_minor' => 0,
            'paid_at' => $placedAt,
        ]);

        return $order;
    }
}
