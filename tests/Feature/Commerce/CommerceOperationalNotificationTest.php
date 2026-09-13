<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Inventory\Events\StockBecameLow;
use App\Modules\Commerce\Inventory\Events\StockDepleted;
use App\Modules\Commerce\Inventory\Services\InventoryService;
use App\Modules\Commerce\Notifications\CustomerOrderCancelledNotification;
use App\Modules\Commerce\Notifications\CustomerOrderReadyNotification;
use App\Modules\Commerce\Notifications\CustomerPaymentConfirmedNotification;
use App\Modules\Commerce\Notifications\LowStockNotification;
use App\Modules\Commerce\Notifications\NewWebOrderReceivedNotification;
use App\Modules\Commerce\Notifications\OutOfStockNotification;
use App\Modules\Commerce\Notifications\QueuedCommerceNotification;
use App\Modules\Commerce\Notifications\TillVarianceNotification;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Events\OrderCancelled;
use App\Modules\Commerce\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Commerce\Orders\Events\OrderReady;
use App\Modules\Commerce\Orders\Events\WebOrderPlaced;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Notifications\OrderConfirmationNotification;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use App\Modules\Commerce\PointOfSale\Events\TillVarianceDetected;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use App\Modules\Commerce\Storefront\Services\StorefrontCheckoutService;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/** Verifies Phase 4.3 event timing, routing, deduplication, and delivery guards. */
final class CommerceOperationalNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    public function test_catalogue_events_and_notifications_use_after_commit_queued_contracts(): void
    {
        $events = [
            new WebOrderPlaced('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new OrderPaymentConfirmed('01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW'),
            new OrderReady('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new OrderCancelled('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new StockBecameLow('01ARZ3NDEKTSV4RRFFQ69G5FAX', 10, 5, 5),
            new StockDepleted('01ARZ3NDEKTSV4RRFFQ69G5FAX', 2, 0),
            new TillVarianceDetected('01ARZ3NDEKTSV4RRFFQ69G5FAY', -10_000, 10_000),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        }

        $notifications = [
            new NewWebOrderReceivedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new CustomerPaymentConfirmedNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAW'),
            new CustomerOrderReadyNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new CustomerOrderCancelledNotification('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            new LowStockNotification('01ARZ3NDEKTSV4RRFFQ69G5FAX', 5, 5),
            new OutOfStockNotification('01ARZ3NDEKTSV4RRFFQ69G5FAX', 0),
            new TillVarianceNotification('01ARZ3NDEKTSV4RRFFQ69G5FAY', -10_000, 10_000),
        ];

        foreach ($notifications as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertTrue($notification->afterCommit);
            $this->assertSame(3, $notification->tries);
            $this->assertSame([30, 120, 300], $notification->backoff());

            $restored = unserialize(serialize($notification));
            $eventEnabled = new \ReflectionMethod(QueuedCommerceNotification::class, 'eventEnabled');
            $this->assertInstanceOf($notification::class, $restored);
            $this->assertTrue($eventEnabled->invoke($restored));
        }
    }

    public function test_web_checkout_notifies_only_active_explicit_order_managers(): void
    {
        $manager = $this->staffWith(CommercePermission::MANAGE_ORDERS, 'orders@example.test');
        $inactive = $this->staffWith(CommercePermission::MANAGE_ORDERS, 'inactive@example.test', false);
        $unprivileged = User::factory()->create(['email' => 'viewer@example.test', 'is_active' => true]);
        $order = $this->placeWebOrder($this->product(stock: 10), 'customer@example.test');

        Notification::assertSentTo(
            $manager,
            NewWebOrderReceivedNotification::class,
            static fn (NewWebOrderReceivedNotification $notification): bool => $notification->orderUlid === $order->ulid,
        );
        Notification::assertNotSentTo($inactive, NewWebOrderReceivedNotification::class);
        Notification::assertNotSentTo($unprivileged, NewWebOrderReceivedNotification::class);
        Notification::assertSentOnDemand(
            OrderConfirmationNotification::class,
            static fn ($notification, array $channels, object $notifiable): bool => $channels === ['mail']
                && data_get($notifiable, 'routes.mail') === 'customer@example.test',
        );
    }

    public function test_completed_web_payment_routes_one_focused_customer_confirmation(): void
    {
        $order = $this->placeWebOrder($this->product(stock: 10), 'payer@example.test');
        Notification::fake();

        $payment = app(PaymentService::class)->recordCompleted(
            $order,
            new PaymentData(PaymentMethod::MobileMoney, $order->total_minor, reference: 'PAY-001'),
        );

        Notification::assertSentOnDemand(
            CustomerPaymentConfirmedNotification::class,
            static fn (CustomerPaymentConfirmedNotification $notification, array $channels, object $notifiable): bool => $notification->orderUlid === $order->ulid
                && $notification->paymentUlid === $payment->ulid
                && $channels === ['mail']
                && data_get($notifiable, 'routes.mail') === 'payer@example.test',
        );

        $mail = (new CustomerPaymentConfirmedNotification($order->ulid, $payment->ulid))
            ->toMail((new AnonymousNotifiable)->route('mail', 'payer@example.test'));
        $this->assertStringContainsString((string) $order->order_number, (string) $mail->subject);
        $this->assertSame('Track your order', $mail->actionText);
    }

    public function test_ready_and_cancelled_order_events_send_independent_customer_messages(): void
    {
        $readyOrder = $this->placeWebOrder($this->product(stock: 10), 'ready@example.test');
        Notification::fake();

        $orders = app(OrderService::class);
        $actor = User::factory()->create();
        foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Ready] as $status) {
            $readyOrder = $orders->transition($readyOrder, $status, $actor);
        }

        Notification::assertSentOnDemand(
            CustomerOrderReadyNotification::class,
            static fn (CustomerOrderReadyNotification $notification, array $channels, object $notifiable): bool => $notification->orderUlid === $readyOrder->ulid
                && data_get($notifiable, 'routes.mail') === 'ready@example.test',
        );
        Notification::assertSentOnDemandTimes(CustomerOrderReadyNotification::class, 1);

        $cancelledOrder = $this->placeWebOrder($this->product(stock: 10), 'cancelled@example.test');
        Notification::fake();
        $cancelledOrder = $orders->cancelWebOrder($cancelledOrder, 'Customer requested cancellation', $actor);

        Notification::assertSentOnDemand(
            CustomerOrderCancelledNotification::class,
            static fn (CustomerOrderCancelledNotification $notification, array $channels, object $notifiable): bool => $notification->orderUlid === $cancelledOrder->ulid
                && data_get($notifiable, 'routes.mail') === 'cancelled@example.test',
        );
        $this->assertSame(OrderStatus::Cancelled, $cancelledOrder->status);
        $this->assertNotNull($cancelledOrder->stock_released_at);
    }

    public function test_stock_alerts_fire_once_per_state_transition_and_rearm_after_replenishment(): void
    {
        $manager = $this->staffWith(CommercePermission::MANAGE_INVENTORY, 'inventory@example.test');
        $inactive = $this->staffWith(CommercePermission::MANAGE_INVENTORY, 'stock-inactive@example.test', false);
        $product = $this->product(stock: 10, threshold: 5);
        $inventory = app(InventoryService::class);
        $actor = User::factory()->create();

        $inventory->adjust($product, -5, 'Threshold test', $actor);
        $inventory->adjust($product, -1, 'Remain low', $actor);
        Notification::assertSentToTimes($manager, LowStockNotification::class, 1);

        $inventory->adjust($product, -4, 'Depletion test', $actor);
        Notification::assertSentToTimes($manager, OutOfStockNotification::class, 1);
        Notification::assertNotSentTo($inactive, LowStockNotification::class);
        Notification::assertNotSentTo($inactive, OutOfStockNotification::class);

        $inventory->adjust($product, 10, 'Replenish and rearm', $actor);
        $inventory->adjust($product, -5, 'Second threshold crossing', $actor);
        Notification::assertSentToTimes($manager, LowStockNotification::class, 2);

        $jumpToZero = $this->product(stock: 10, threshold: 5);
        $inventory->adjust($jumpToZero, -10, 'Direct depletion', $actor);
        $lowForJump = Notification::sent($manager, LowStockNotification::class)
            ->filter(static fn (LowStockNotification $notification): bool => $notification->productUlid === $jumpToZero->ulid);
        $this->assertCount(0, $lowForJump);
        Notification::assertSentTo(
            $manager,
            OutOfStockNotification::class,
            static fn (OutOfStockNotification $notification): bool => $notification->productUlid === $jumpToZero->ulid,
        );
    }

    public function test_till_variance_alert_uses_absolute_materiality_and_manage_tills_recipients(): void
    {
        config(['commerce.notifications.till_variance_threshold_minor' => 100]);
        $manager = $this->staffWith(CommercePermission::MANAGE_TILLS, 'tills@example.test');
        $cashier = User::factory()->create();
        $tills = app(TillService::class);

        $immaterial = $tills->open(Register::factory()->create(), $cashier, 1_000);
        $tills->close($immaterial, $cashier, 1_099);
        Notification::assertNotSentTo($manager, TillVarianceNotification::class);

        $material = $tills->open(Register::factory()->create(), $cashier, 1_000);
        $closed = $tills->close($material, $cashier, 900);
        Notification::assertSentTo(
            $manager,
            TillVarianceNotification::class,
            static fn (TillVarianceNotification $notification): bool => $notification->tillSessionUlid === $closed->ulid
                && $notification->varianceMinor === -100,
        );
        Notification::assertSentToTimes($manager, TillVarianceNotification::class, 1);
    }

    public function test_disabled_configuration_missing_email_and_stale_state_fail_closed(): void
    {
        $manager = $this->staffWith(CommercePermission::MANAGE_INVENTORY, 'guarded@example.test');
        $product = $this->product(stock: 10, threshold: 5);
        config(['commerce.notifications.enabled' => false]);

        app(InventoryService::class)->adjust($product, -5, 'Disabled alert test', User::factory()->create());
        Notification::assertNothingSent();

        config(['commerce.notifications.enabled' => true]);
        $notification = new LowStockNotification($product->ulid, 5, 5);
        app(InventoryService::class)->adjust($product, 5, 'Restore before queued delivery', User::factory()->create());
        $this->assertFalse($notification->shouldSend($manager->fresh(), 'mail'));

        $readyWithoutEmail = Order::factory()->create([
            'status' => OrderStatus::Ready,
            'customer_email' => null,
        ]);
        OrderReady::dispatch($readyWithoutEmail->ulid);
        Notification::assertNothingSent();
    }

    public function test_after_commit_stock_event_is_discarded_when_outer_transaction_rolls_back(): void
    {
        $this->staffWith(CommercePermission::MANAGE_INVENTORY, 'rollback@example.test');
        $product = $this->product(stock: 10, threshold: 5);

        try {
            DB::transaction(function () use ($product): void {
                app(InventoryService::class)->adjust(
                    $product,
                    -5,
                    'Rollback notification test',
                    User::factory()->create(),
                );

                throw new RuntimeException('Force rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback', $exception->getMessage());
        }

        $this->assertSame(10, $product->stock()->firstOrFail()->on_hand);
        Notification::assertNothingSent();
    }

    private function staffWith(string $permission, string $email, bool $active = true): User
    {
        $user = User::factory()->create(['email' => $email, 'is_active' => $active]);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function product(int $stock, int $threshold = 5): Product
    {
        $product = Product::factory()->published()->withStock($stock)->create([
            'price_minor' => 10_000,
            'sale_price_minor' => null,
        ]);
        $product->stock()->update(['low_stock_threshold' => $threshold]);

        return $product->refresh()->load('stock');
    }

    private function placeWebOrder(Product $product, ?string $email): Order
    {
        return app(StorefrontCheckoutService::class)->place(new OrderPlacementData(
            items: [new CartItemData($product->id, 1)],
            customer: new CustomerSnapshotData(
                firstName: 'Amina',
                lastName: 'Otieno',
                email: $email,
                phone: '+254700000001',
            ),
            fulfillmentType: FulfillmentType::Pickup,
            preferredPaymentMethod: PaymentMethod::MobileMoney,
        ));
    }
}
