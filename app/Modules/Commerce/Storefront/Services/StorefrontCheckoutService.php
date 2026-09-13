<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Services;

use App\Models\User;
use App\Modules\Commerce\Customers\Services\CustomerService;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Events\WebOrderPlaced;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Notifications\OrderConfirmationNotification;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Coordinates atomic storefront placement and post-commit customer messaging.
 */
final readonly class StorefrontCheckoutService
{
    public function __construct(
        private DatabaseManager $database,
        private CustomerService $customers,
        private OrderService $orders,
        private PaymentService $payments,
    ) {}

    /**
     * Commit one web order and its pending manual-payment preference.
     */
    public function place(OrderPlacementData $data, ?User $account = null): Order
    {
        $order = $this->database->transaction(function () use ($data, $account): Order {
            $customer = $this->customers->resolveForCheckout($data->customer, $account);
            $order = $this->orders->placeWebOrder(
                data: $data,
                customer: $customer,
                account: $account,
            );

            if ($data->preferredPaymentMethod !== null) {
                $this->payments->recordPendingPreference($order, $data->preferredPaymentMethod);
            }

            return $order->refresh()->load(['items.product', 'payments']);
        });

        WebOrderPlaced::dispatch($order->ulid);
        $this->queueConfirmation($order);

        return $order;
    }

    private function queueConfirmation(Order $order): void
    {
        if (blank($order->customer_email)) {
            return;
        }

        try {
            Notification::route('mail', $order->customer_email)
                ->notify(new OrderConfirmationNotification($order->ulid));
        } catch (Throwable $exception) {
            Log::error('Commerce order confirmation could not be queued.', [
                'order_ulid' => $order->ulid,
                'exception' => $exception::class,
            ]);
            report($exception);
        }
    }
}
