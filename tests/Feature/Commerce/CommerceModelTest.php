<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies Commerce model casts, relationships, derived state, and media compatibility.
 */
final class CommerceModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the complete relationship graph and verify typed domain state.
     */
    public function test_factories_create_a_connected_typed_commerce_graph(): void
    {
        $parent = ProductCategory::factory()->create();
        $category = ProductCategory::factory()->childOf($parent)->create();
        $product = Product::factory()->for($category, 'category')->published()->onSale(8_000)->create();
        $stock = Stock::factory()->for($product)->create(['on_hand' => 12, 'low_stock_threshold' => 3]);
        $customer = Customer::factory()->create();
        $register = Register::factory()->create();
        $cashier = User::factory()->create();
        $till = TillSession::factory()->for($register)->create(['opened_by' => $cashier->id]);
        $order = Order::factory()->for($customer)->create([
            'channel' => OrderChannel::PointOfSale,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'fulfillment_type' => FulfillmentType::Counter,
            'preferred_payment_method' => PaymentMethod::Cash,
            'register_id' => $register->id,
            'till_session_id' => $till->id,
            'cashier_id' => $cashier->id,
            'paid_minor' => 10_000,
            'completed_at' => now(),
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create();
        $payment = Payment::factory()->for($order)->for($till, 'tillSession')->completed()->create([
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Completed,
        ]);
        $movement = StockMovement::factory()->for($product)->create([
            'type' => StockMovementType::OrderCommit,
            'quantity_delta' => -2,
            'balance_before' => 14,
            'balance_after' => 12,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);

        $this->assertTrue($parent->children->contains($category));
        $this->assertTrue($category->products->contains($product));
        $this->assertSame(ProductStatus::Published, $product->status);
        $this->assertTrue($product->isOnSale());
        $this->assertSame(8_000, $product->effective_price_minor);
        $this->assertTrue($product->isInStock());
        $this->assertSame(12, $stock->available());
        $this->assertFalse($stock->isLow());
        $this->assertSame(TillSessionStatus::Open, $till->status);
        $this->assertTrue($till->isOpen());
        $this->assertSame(OrderChannel::PointOfSale, $order->channel);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(0, $order->balance_due_minor);
        $this->assertTrue($order->isFullyPaid());
        $this->assertTrue($order->items->contains($item));
        $this->assertTrue($order->payments->contains($payment));
        $this->assertTrue($payment->isCompleted());
        $this->assertTrue($order->stockMovements->contains($movement));
        $this->assertTrue($movement->reference->is($order));
        $this->assertSame($customer->first_name.' '.$customer->last_name, $customer->display_name);
    }

    /**
     * Verify product media remains attached through the integer primary key.
     */
    public function test_product_gallery_preserves_integer_media_polymorphism(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $media = $product
            ->addMedia(UploadedFile::fake()->image('product.webp', 600, 600))
            ->toMediaCollection('product_gallery');

        $this->assertSame($product->id, $media->model_id);
        $this->assertSame(Product::class, $media->model_type);
        $this->assertSame($product->ulid, $product->getRouteKey());
        $this->assertTrue($product->fresh()->hasMedia('product_gallery'));
    }

    /**
     * Verify held orders are intentionally unnumbered and do not imply stock commitment.
     */
    public function test_held_pos_order_factory_preserves_hold_invariants(): void
    {
        $order = Order::factory()->held()->create();

        $this->assertSame(OrderChannel::PointOfSale, $order->channel);
        $this->assertSame(OrderStatus::Held, $order->status);
        $this->assertNull($order->order_number);
        $this->assertNull($order->stock_committed_at);
        $this->assertNull($order->placed_at);
    }
}
