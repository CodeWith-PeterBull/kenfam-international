<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Services\ProductService;
use App\Modules\Commerce\Exceptions\PaymentMismatchException;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Services\PosCheckoutService;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies till lifecycle, split tender, hold repricing, and POS rollback rules.
 */
final class CommercePosTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_till_service_enforces_one_open_session_per_register_and_cashier(): void
    {
        $cashier = User::factory()->create();
        $otherCashier = User::factory()->create();
        $register = Register::factory()->create();
        $otherRegister = Register::factory()->create();
        $service = app(TillService::class);

        $session = $service->open($register, $cashier, 10_000, 'Morning shift');
        $this->assertSame(TillSessionStatus::Open, $session->status);
        $this->assertSame(10_000, $session->expected_cash_minor);

        foreach ([[$register, $otherCashier], [$otherRegister, $cashier]] as [$blockedRegister, $blockedCashier]) {
            try {
                $service->open($blockedRegister, $blockedCashier, 0);
                $this->fail('A conflicting till session was opened.');
            } catch (TillSessionException $exception) {
                $this->assertStringContainsString('already has an open', $exception->getMessage());
            }
        }
    }

    public function test_pos_checkout_atomically_records_split_payment_stock_and_cash(): void
    {
        $cashier = User::factory()->create();
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 10_000);
        $product = $this->product(price: 10_000, stock: 5);
        $order = app(PosCheckoutService::class)->checkout(
            data: $this->placement($product),
            payments: [
                new PaymentData(
                    method: PaymentMethod::Cash,
                    amountMinor: 4_000,
                    tenderedMinor: 5_000,
                    metadata: ['card_number' => '4111111111111111', 'terminal' => 'front-desk'],
                ),
                new PaymentData(
                    method: PaymentMethod::MobileMoney,
                    amountMinor: 6_000,
                    reference: 'MPESA-TEST-001',
                ),
            ],
            session: $session,
            cashier: $cashier,
        );

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(10_000, $order->paid_minor);
        $this->assertMatchesRegularExpression('/^POS-\d{8}-\d{8}$/', $order->order_number);
        $this->assertSame(4, $product->stock()->firstOrFail()->on_hand);
        $this->assertSame(2, $order->payments->count());

        $cashPayment = $order->payments->firstWhere('method', PaymentMethod::Cash);
        $this->assertSame(1_000, $cashPayment->change_minor);
        $this->assertSame('[REDACTED]', $cashPayment->metadata['card_number']);
        $this->assertSame('front-desk', $cashPayment->metadata['terminal']);
        $this->assertSame(14_000, $session->fresh()->expected_cash_minor);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.pos.sale_completed']);

        $closed = app(TillService::class)->close($session, $cashier, 14_050, 'Fifty over');
        $this->assertSame(TillSessionStatus::Closed, $closed->status);
        $this->assertSame(14_000, $closed->expected_cash_minor);
        $this->assertSame(50, $closed->variance_minor);
    }

    public function test_underpaid_pos_checkout_rolls_back_every_transactional_write(): void
    {
        $cashier = User::factory()->create();
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 2_000);
        $product = $this->product(price: 10_000, stock: 4);

        try {
            app(PosCheckoutService::class)->checkout(
                data: $this->placement($product),
                payments: [new PaymentData(PaymentMethod::Cash, 5_000, 5_000)],
                session: $session,
                cashier: $cashier,
            );
            $this->fail('An underpaid POS sale was committed.');
        } catch (PaymentMismatchException $exception) {
            $this->assertStringContainsString('complete order total', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(4, $product->stock()->firstOrFail()->on_hand);
        $this->assertSame(2_000, $session->fresh()->expected_cash_minor);
        $this->assertDatabaseMissing('system_activities', ['activity_type' => 'commerce.order.placed']);
    }

    public function test_held_order_is_repriced_and_completed_without_changing_its_ulid(): void
    {
        $cashier = User::factory()->create();
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 0);
        $product = $this->product(price: 10_000, stock: 3);
        $data = $this->placement($product, quantity: 1);
        $held = app(OrderService::class)->holdPointOfSaleOrder($data, $session, $cashier);

        $this->assertSame(OrderStatus::Held, $held->status);
        $this->assertNull($held->order_number);
        $this->assertNull($held->stock_committed_at);
        $this->assertSame(3, $product->stock()->firstOrFail()->on_hand);

        app(ProductService::class)->updateProduct($product, ['price_minor' => 12_000], $cashier);
        $completed = app(PosCheckoutService::class)->checkoutHeld(
            heldOrder: $held,
            data: $data,
            payments: [new PaymentData(PaymentMethod::Cash, 12_000, 12_000)],
            session: $session,
            cashier: $cashier,
        );

        $this->assertSame($held->id, $completed->id);
        $this->assertSame($held->ulid, $completed->ulid);
        $this->assertSame(12_000, $completed->total_minor);
        $this->assertSame(12_000, $completed->items->sole()->unit_price_minor);
        $this->assertSame(OrderStatus::Completed, $completed->status);
        $this->assertSame(2, $product->stock()->firstOrFail()->on_hand);
    }

    public function test_till_cannot_close_with_unresolved_held_orders(): void
    {
        $cashier = User::factory()->create();
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 0);
        $product = $this->product();
        app(OrderService::class)->holdPointOfSaleOrder($this->placement($product), $session, $cashier);

        $this->expectException(TillSessionException::class);
        $this->expectExceptionMessage('Resolve held orders');
        app(TillService::class)->close($session, $cashier, 0);
    }

    public function test_cashier_can_discard_only_an_owned_held_order_without_stock_mutation(): void
    {
        $cashier = User::factory()->create();
        $otherCashier = User::factory()->create();
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 0);
        $product = $this->product(stock: 3);
        $held = app(OrderService::class)->holdPointOfSaleOrder($this->placement($product), $session, $cashier);

        try {
            app(OrderService::class)->discardHeldPointOfSaleOrder($held, $session, $otherCashier);
            $this->fail('A different cashier discarded the held order.');
        } catch (TillSessionException $exception) {
            $this->assertStringContainsString('another cashier', $exception->getMessage());
        }

        $discarded = app(OrderService::class)->discardHeldPointOfSaleOrder($held, $session, $cashier);
        $this->assertSame(OrderStatus::Cancelled, $discarded->status);
        $this->assertNull($discarded->stock_committed_at);
        $this->assertSame(3, $product->stock()->firstOrFail()->on_hand);
    }

    private function product(int $price = 10_000, int $stock = 10): Product
    {
        return Product::factory()->published()->withStock($stock)->create([
            'price_minor' => $price,
            'sale_price_minor' => null,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => true,
        ]);
    }

    private function placement(Product $product, int $quantity = 1): OrderPlacementData
    {
        return new OrderPlacementData(
            items: [new CartItemData($product->id, $quantity)],
            customer: new CustomerSnapshotData(firstName: 'Walk-in', lastName: 'Customer'),
            fulfillmentType: FulfillmentType::Counter,
            preferredPaymentMethod: PaymentMethod::Cash,
        );
    }
}
