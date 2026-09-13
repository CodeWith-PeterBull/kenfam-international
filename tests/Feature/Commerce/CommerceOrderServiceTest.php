<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Exceptions\InsufficientStockException;
use App\Modules\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies atomic web placement, snapshot persistence, transitions, and cancellation.
 */
final class CommerceOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_order_recalculates_snapshots_numbers_and_commits_stock(): void
    {
        $actor = User::factory()->create();
        $product = $this->product(price: 12_500, stock: 8);

        $order = app(OrderService::class)->placeWebOrder(
            $this->placement($product, quantity: 2, delivery: 500),
            actor: $actor,
        );

        $this->assertSame(OrderChannel::Web, $order->channel);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertMatchesRegularExpression('/^WEB-\d{8}-\d{8}$/', $order->order_number);
        $this->assertSame(25_000, $order->subtotal_minor);
        $this->assertSame(25_500, $order->total_minor);
        $this->assertNotNull($order->placed_at);
        $this->assertNotNull($order->stock_committed_at);
        $this->assertSame(1, $order->items->count());
        $this->assertSame($product->name, $order->items->first()->product_name);
        $this->assertSame(12_500, $order->items->first()->unit_price_minor);
        $this->assertSame(6, $product->stock()->firstOrFail()->on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::OrderCommit->value,
            'quantity_delta' => -2,
            'balance_before' => 8,
            'balance_after' => 6,
            'reference_id' => $order->id,
        ]);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.order.placed']);
    }

    public function test_insufficient_stock_rolls_back_the_complete_order_transaction(): void
    {
        $product = $this->product(price: 10_000, stock: 1);

        try {
            app(OrderService::class)->placeWebOrder($this->placement($product, quantity: 2));
            $this->fail('An order was placed without sufficient stock.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(2, $exception->requested);
            $this->assertSame(1, $exception->available);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, $product->stock()->firstOrFail()->on_hand);
    }

    public function test_web_cancellation_restores_stock_exactly_once(): void
    {
        $actor = User::factory()->create();
        $product = $this->product(price: 10_000, stock: 5);
        $service = app(OrderService::class);
        $order = $service->placeWebOrder($this->placement($product, quantity: 3), actor: $actor);

        $cancelled = $service->cancelWebOrder($order, 'Customer requested cancellation', $actor);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->stock_released_at);
        $this->assertSame(5, $product->stock()->firstOrFail()->on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::OrderCancel->value,
            'quantity_delta' => 3,
            'reference_id' => $order->id,
        ]);
        $this->assertSame(2, $order->stockMovements()->count());

        try {
            $service->cancelWebOrder($cancelled, 'Second cancellation', $actor);
            $this->fail('A cancelled order restored stock twice.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame(5, $product->stock()->firstOrFail()->on_hand);
            $this->assertSame(2, $order->stockMovements()->count());
        }
    }

    public function test_fulfillment_transitions_follow_the_explicit_state_map(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $service = app(OrderService::class);
        $order = $service->placeWebOrder($this->placement($product), actor: $actor);

        foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
            $order = $service->transition($order, $status, $actor);
            $this->assertSame($status, $order->status);
        }
        $this->assertNotNull($order->completed_at);

        $this->expectException(InvalidOrderTransitionException::class);
        $service->transition($order, OrderStatus::Processing, $actor);
    }

    public function test_paid_web_order_cannot_be_cancelled_without_a_refund_workflow(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $service = app(OrderService::class);
        $order = $service->placeWebOrder($this->placement($product), actor: $actor);
        app(PaymentService::class)->recordCompleted(
            $order,
            new PaymentData(PaymentMethod::BankTransfer, $order->total_minor, reference: 'BANK-001'),
            actor: $actor,
        );

        $this->expectException(InvalidOrderTransitionException::class);
        $service->cancelWebOrder($order, 'Attempt without refund', $actor);
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

    private function placement(
        Product $product,
        int $quantity = 1,
        int $delivery = 0,
    ): OrderPlacementData {
        return new OrderPlacementData(
            items: [new CartItemData($product->id, $quantity)],
            customer: new CustomerSnapshotData(
                firstName: 'Peter',
                lastName: 'Mwangi',
                email: 'peter@example.test',
                addressLine1: '14 Riverside Drive',
                city: 'Nairobi',
            ),
            fulfillmentType: $delivery > 0 ? FulfillmentType::Delivery : FulfillmentType::Pickup,
            deliveryFeeMinor: $delivery,
        );
    }
}
