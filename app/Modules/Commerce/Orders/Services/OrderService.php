<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\Inventory\Services\InventoryService;
use App\Modules\Commerce\Orders\Data\CalculatedCartLine;
use App\Modules\Commerce\Orders\Data\CartCalculation;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Events\OrderCancelled;
use App\Modules\Commerce\Orders\Events\OrderReady;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Database\DatabaseManager;

/**
 * Owns order snapshots, numbering, stock commitment, and lifecycle transitions.
 */
final readonly class OrderService
{
    public function __construct(
        private DatabaseManager $database,
        private CartCalculator $calculator,
        private OrderNumberService $numbers,
        private InventoryService $inventory,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * Place a web order using freshly locked products and server-calculated totals.
     */
    public function placeWebOrder(
        OrderPlacementData $data,
        ?Customer $customer = null,
        ?User $account = null,
        ?User $actor = null,
    ): Order {
        if ($data->fulfillmentType === FulfillmentType::Counter) {
            throw new InvalidOrderTransitionException('Counter fulfillment is reserved for point-of-sale orders.');
        }

        return $this->database->transaction(function () use ($data, $customer, $account, $actor): Order {
            $calculation = $this->calculateWithLockedProducts($data);
            $order = $this->newOrder(
                data: $data,
                calculation: $calculation,
                channel: OrderChannel::Web,
                status: OrderStatus::Pending,
                customer: $customer,
                account: $account,
                actor: $actor,
            );
            $this->numbers->assign($order);
            $this->createItems($order, $calculation);
            $this->inventory->commitOrder($order, $actor);
            $order->forceFill(['placed_at' => now()])->save();
            $this->recordPlacedActivity($order, $actor);

            return $this->freshAggregate($order);
        });
    }

    /**
     * Persist an unnumbered POS hold without consuming stock.
     */
    public function holdPointOfSaleOrder(
        OrderPlacementData $data,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
    ): Order {
        if ($data->fulfillmentType !== FulfillmentType::Counter) {
            throw new InvalidOrderTransitionException('Held point-of-sale orders require counter fulfillment.');
        }

        return $this->database->transaction(function () use ($data, $session, $cashier, $customer): Order {
            $session = $this->lockedUsableTill($session, $cashier);
            $calculation = $this->calculateWithLockedProducts($data);
            $order = $this->newOrder(
                data: $data,
                calculation: $calculation,
                channel: OrderChannel::PointOfSale,
                status: OrderStatus::Held,
                customer: $customer,
                actor: $cashier,
                session: $session,
            );
            $this->createItems($order, $calculation);

            $this->activities->record(
                activityType: 'commerce.pos.order_held',
                description: "POS order held by {$cashier->display_name}",
                actor: $cashier,
                subject: $order,
                properties: [
                    'till_ulid' => $session->ulid,
                    'line_count' => count($calculation->lines),
                    'total_minor' => $calculation->totalMinor,
                ],
                source: 'commerce-pos',
            );

            return $this->freshAggregate($order);
        });
    }

    /**
     * Create a completed POS order inside PosCheckoutService's outer transaction.
     */
    public function placePointOfSaleOrder(
        OrderPlacementData $data,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
    ): Order {
        if ($data->fulfillmentType !== FulfillmentType::Counter) {
            throw new InvalidOrderTransitionException('Point-of-sale orders require counter fulfillment.');
        }

        return $this->database->transaction(function () use ($data, $session, $cashier, $customer): Order {
            $session = $this->lockedUsableTill($session, $cashier);
            $calculation = $this->calculateWithLockedProducts($data);
            $order = $this->newOrder(
                data: $data,
                calculation: $calculation,
                channel: OrderChannel::PointOfSale,
                status: OrderStatus::Completed,
                customer: $customer,
                actor: $cashier,
                session: $session,
            );
            $this->numbers->assign($order);
            $this->createItems($order, $calculation);
            $this->inventory->commitOrder($order, $cashier);
            $order->forceFill(['placed_at' => now(), 'completed_at' => now()])->save();
            $this->recordPlacedActivity($order, $cashier);

            return $this->freshAggregate($order);
        });
    }

    /**
     * Reprice and complete a held POS order while preserving its external ULID.
     */
    public function completeHeldPointOfSaleOrder(
        Order $heldOrder,
        OrderPlacementData $data,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
    ): Order {
        return $this->database->transaction(function () use ($heldOrder, $data, $session, $cashier, $customer): Order {
            $session = $this->lockedUsableTill($session, $cashier);
            $heldOrder = Order::query()->lockForUpdate()->findOrFail($heldOrder->getKey());

            if ($heldOrder->status !== OrderStatus::Held
                || $heldOrder->channel !== OrderChannel::PointOfSale
                || $heldOrder->stock_committed_at !== null
                || $heldOrder->order_number !== null) {
                throw InvalidOrderTransitionException::forOrder($heldOrder, OrderStatus::Completed);
            }

            if ($heldOrder->till_session_id !== $session->getKey()) {
                throw new TillSessionException('The held order belongs to a different till session.');
            }

            $calculation = $this->calculateWithLockedProducts($data);
            $heldOrder->forceFill($this->orderAttributes(
                data: $data,
                calculation: $calculation,
                channel: OrderChannel::PointOfSale,
                status: OrderStatus::Completed,
                customer: $customer,
                actor: $cashier,
                session: $session,
            ));
            $heldOrder->save();
            $heldOrder->items()->delete();
            $this->createItems($heldOrder, $calculation);
            $this->numbers->assign($heldOrder);
            $this->inventory->commitOrder($heldOrder, $cashier);
            $heldOrder->forceFill(['placed_at' => now(), 'completed_at' => now()])->save();
            $this->recordPlacedActivity($heldOrder, $cashier);

            return $this->freshAggregate($heldOrder);
        });
    }

    /**
     * Cancel an unresolved POS hold without committing or releasing stock.
     */
    public function discardHeldPointOfSaleOrder(
        Order $heldOrder,
        TillSession $session,
        User $cashier,
        ?string $reason = null,
    ): Order {
        return $this->database->transaction(function () use ($heldOrder, $session, $cashier, $reason): Order {
            $session = $this->lockedUsableTill($session, $cashier);
            $heldOrder = Order::query()->lockForUpdate()->findOrFail($heldOrder->getKey());

            if ($heldOrder->status !== OrderStatus::Held
                || $heldOrder->channel !== OrderChannel::PointOfSale
                || $heldOrder->till_session_id !== $session->getKey()
                || $heldOrder->cashier_id !== $cashier->getKey()
                || $heldOrder->stock_committed_at !== null
                || $heldOrder->order_number !== null) {
                throw new InvalidOrderTransitionException('Only this cashier\'s unresolved hold may be discarded.');
            }

            $reason = trim((string) $reason);
            $heldOrder->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancellation_reason' => $reason === '' ? 'Discarded at point of sale' : mb_substr($reason, 0, 255),
                'cancelled_at' => now(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.pos.hold_discarded',
                description: 'POS hold discarded',
                actor: $cashier,
                subject: $heldOrder,
                properties: [
                    'till_ulid' => $session->ulid,
                    'order_ulid' => $heldOrder->ulid,
                    'total_minor' => $heldOrder->total_minor,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-pos',
            );

            return $this->freshAggregate($heldOrder);
        });
    }

    /**
     * Advance an eligible non-held order through the fulfillment lifecycle.
     */
    public function transition(Order $order, OrderStatus $target, User $actor): Order
    {
        return $this->database->transaction(function () use ($order, $target, $actor): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $allowed = match ($order->status) {
                OrderStatus::Pending => [OrderStatus::Confirmed],
                OrderStatus::Confirmed => [OrderStatus::Processing],
                OrderStatus::Processing => [OrderStatus::Ready],
                OrderStatus::Ready => [OrderStatus::Completed],
                default => [],
            };

            if (! in_array($target, $allowed, true)) {
                throw InvalidOrderTransitionException::forOrder($order, $target);
            }

            $previous = $order->status;
            $order->forceFill([
                'status' => $target,
                'completed_at' => $target === OrderStatus::Completed ? now() : $order->completed_at,
            ])->save();

            $this->activities->record(
                activityType: 'commerce.order.status_changed',
                description: "Order {$order->order_number} changed to {$target->label()}",
                actor: $actor,
                subject: $order,
                properties: ['from' => $previous, 'to' => $target],
                source: 'commerce-orders',
            );

            if ($order->channel === OrderChannel::Web && $target === OrderStatus::Ready) {
                OrderReady::dispatch($order->ulid);
            }

            return $this->freshAggregate($order);
        });
    }

    /**
     * Cancel an eligible web order and restore committed stock exactly once.
     */
    public function cancelWebOrder(Order $order, string $reason, User $actor): Order
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 255) {
            throw new InvalidOrderTransitionException('Order cancellation requires a reason of 255 characters or fewer.');
        }

        return $this->database->transaction(function () use ($order, $reason, $actor): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $eligibleStatuses = [
                OrderStatus::Pending,
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Ready,
            ];

            if ($order->channel !== OrderChannel::Web
                || ! in_array($order->status, $eligibleStatuses, true)
                || $order->payment_status !== OrderPaymentStatus::Unpaid
                || $order->paid_minor !== 0
                || $order->stock_committed_at === null
                || $order->stock_released_at !== null) {
                throw InvalidOrderTransitionException::forOrder($order, OrderStatus::Cancelled);
            }

            $previous = $order->status;
            $this->inventory->releaseOrder($order, $actor);
            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            $this->activities->record(
                activityType: 'commerce.order.cancelled',
                description: "Order {$order->order_number} cancelled",
                actor: $actor,
                subject: $order,
                properties: ['from' => $previous, 'stock_restored' => true],
                severity: SystemActivitySeverity::Warning,
                source: 'commerce-orders',
            );

            OrderCancelled::dispatch($order->ulid);

            return $this->freshAggregate($order);
        });
    }

    private function calculateWithLockedProducts(OrderPlacementData $data): CartCalculation
    {
        $productIds = collect($data->items)->pluck('productId')->sort()->values();
        $products = Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $this->calculator->calculate($data, $products);
    }

    private function newOrder(
        OrderPlacementData $data,
        CartCalculation $calculation,
        OrderChannel $channel,
        OrderStatus $status,
        ?Customer $customer = null,
        ?User $account = null,
        ?User $actor = null,
        ?TillSession $session = null,
    ): Order {
        $order = new Order;
        $order->forceFill($this->orderAttributes(
            data: $data,
            calculation: $calculation,
            channel: $channel,
            status: $status,
            customer: $customer,
            account: $account,
            actor: $actor,
            session: $session,
        ));
        $order->save();

        return $order;
    }

    /** @return array<string, mixed> */
    private function orderAttributes(
        OrderPlacementData $data,
        CartCalculation $calculation,
        OrderChannel $channel,
        OrderStatus $status,
        ?Customer $customer = null,
        ?User $account = null,
        ?User $actor = null,
        ?TillSession $session = null,
    ): array {
        return [
            'order_number' => null,
            'channel' => $channel,
            'status' => $status,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'fulfillment_type' => $data->fulfillmentType,
            'preferred_payment_method' => $data->preferredPaymentMethod,
            'customer_id' => $customer?->getKey(),
            'user_id' => $account?->getKey(),
            'register_id' => $session?->register_id,
            'till_session_id' => $session?->getKey(),
            'cashier_id' => $channel === OrderChannel::PointOfSale ? $actor?->getKey() : null,
            'created_by' => $actor?->getKey(),
            'currency' => $calculation->currency,
            'subtotal_minor' => $calculation->subtotalMinor,
            'discount_minor' => $calculation->discountMinor,
            'discount_reason' => $data->discountMinor > 0 ? trim((string) $data->discountReason) ?: null : null,
            'delivery_fee_minor' => $calculation->deliveryFeeMinor,
            'tax_minor' => $calculation->taxMinor,
            'total_minor' => $calculation->totalMinor,
            'paid_minor' => 0,
            'tax_inclusive' => $calculation->taxInclusive,
            'customer_first_name' => $data->customer->firstName,
            'customer_last_name' => $data->customer->lastName,
            'customer_company' => $data->customer->company,
            'customer_tax_identifier' => $data->customer->taxIdentifier,
            'customer_email' => $data->customer->email,
            'customer_phone' => $data->customer->phone,
            'address_line_1' => $data->customer->addressLine1,
            'address_line_2' => $data->customer->addressLine2,
            'city' => $data->customer->city,
            'region' => $data->customer->region,
            'postal_code' => $data->customer->postalCode,
            'country_code' => $data->customer->countryCode,
            'customer_note' => trim((string) $data->customerNote) ?: null,
            'internal_note' => trim((string) $data->internalNote) ?: null,
            'cancellation_reason' => null,
            'stock_committed_at' => null,
            'stock_released_at' => null,
            'placed_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    private function createItems(Order $order, CartCalculation $calculation): void
    {
        foreach ($calculation->lines as $line) {
            $item = new OrderItem;
            $item->forceFill($this->itemAttributes($order, $line))->save();
        }
    }

    /** @return array<string, mixed> */
    private function itemAttributes(Order $order, CalculatedCartLine $line): array
    {
        return [
            'order_id' => $order->getKey(),
            'product_id' => $line->product->getKey(),
            'product_name' => $line->product->name,
            'sku' => $line->product->sku,
            'quantity' => $line->quantity,
            'unit_price_minor' => $line->unitPriceMinor,
            'unit_cost_minor' => $line->unitCostMinor,
            'line_subtotal_minor' => $line->subtotalMinor,
            'discount_minor' => $line->discountMinor,
            'tax_rate_bps' => $line->taxRateBps,
            'is_tax_inclusive' => $line->isTaxInclusive,
            'tax_minor' => $line->taxMinor,
            'line_total_minor' => $line->totalMinor,
        ];
    }

    private function lockedUsableTill(TillSession $session, User $cashier): TillSession
    {
        $session = TillSession::query()->with('register')->lockForUpdate()->findOrFail($session->getKey());

        if (! $session->isOpen() || ! $session->register->is_active) {
            throw new TillSessionException('The selected till session is not open on an active register.');
        }

        if ($session->opened_by !== $cashier->getKey()) {
            throw new TillSessionException('The selected till session belongs to another cashier.');
        }

        return $session;
    }

    private function recordPlacedActivity(Order $order, ?User $actor): void
    {
        $this->activities->record(
            activityType: 'commerce.order.placed',
            description: "Order {$order->order_number} placed",
            actor: $actor,
            subject: $order,
            properties: [
                'channel' => $order->channel,
                'line_count' => $order->items()->count(),
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
            ],
            severity: SystemActivitySeverity::Notice,
            source: 'commerce-orders',
        );
    }

    private function freshAggregate(Order $order): Order
    {
        return $order->refresh()->load(['items.product', 'payments', 'customer', 'register', 'tillSession']);
    }
}
