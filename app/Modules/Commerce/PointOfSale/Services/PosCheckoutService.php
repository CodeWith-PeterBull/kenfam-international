<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Exceptions\PaymentMismatchException;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Database\DatabaseManager;

/**
 * Atomically orchestrates POS order, inventory, payment, and till mutations.
 */
final readonly class PosCheckoutService
{
    public function __construct(
        private DatabaseManager $database,
        private OrderService $orders,
        private PaymentService $payments,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * Complete a new sale and require exact settlement before commit.
     *
     * @param  list<PaymentData>  $payments
     */
    public function checkout(
        OrderPlacementData $data,
        array $payments,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
    ): Order {
        return $this->complete(
            data: $data,
            paymentData: $payments,
            session: $session,
            cashier: $cashier,
            customer: $customer,
        );
    }

    /**
     * Reprice a persisted hold and complete it under the same transaction rules.
     *
     * @param  list<PaymentData>  $payments
     */
    public function checkoutHeld(
        Order $heldOrder,
        OrderPlacementData $data,
        array $payments,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
    ): Order {
        return $this->complete(
            data: $data,
            paymentData: $payments,
            session: $session,
            cashier: $cashier,
            customer: $customer,
            heldOrder: $heldOrder,
        );
    }

    /**
     * @param  list<PaymentData>  $paymentData
     */
    private function complete(
        OrderPlacementData $data,
        array $paymentData,
        TillSession $session,
        User $cashier,
        ?Customer $customer = null,
        ?Order $heldOrder = null,
    ): Order {
        if ($paymentData === []) {
            throw new PaymentMismatchException('A POS sale requires at least one payment.');
        }

        foreach ($paymentData as $payment) {
            if (! $payment instanceof PaymentData) {
                throw new PaymentMismatchException('POS payments must use PaymentData values.');
            }
        }

        return $this->database->transaction(function () use ($data, $paymentData, $session, $cashier, $customer, $heldOrder): Order {
            $lockedSession = TillSession::query()->lockForUpdate()->findOrFail($session->getKey());
            if (! $lockedSession->isOpen() || $lockedSession->opened_by !== $cashier->getKey()) {
                throw new TillSessionException('The cashier requires their own open till session.');
            }

            $order = $heldOrder === null
                ? $this->orders->placePointOfSaleOrder($data, $lockedSession, $cashier, $customer)
                : $this->orders->completeHeldPointOfSaleOrder($heldOrder, $data, $lockedSession, $cashier, $customer);

            foreach ($paymentData as $payment) {
                $this->payments->recordCompleted($order, $payment, $lockedSession, $cashier);
            }

            $order->refresh();
            if (! $order->isFullyPaid()) {
                throw new PaymentMismatchException('POS payments must settle the complete order total.');
            }

            $this->activities->record(
                activityType: 'commerce.pos.sale_completed',
                description: "POS sale {$order->order_number} completed",
                actor: $cashier,
                subject: $order,
                properties: [
                    'till_ulid' => $lockedSession->ulid,
                    'total_minor' => $order->total_minor,
                    'payment_count' => count($paymentData),
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-pos',
            );

            return $order->refresh()->load(['items.product', 'payments', 'customer', 'register', 'tillSession']);
        });
    }
}
