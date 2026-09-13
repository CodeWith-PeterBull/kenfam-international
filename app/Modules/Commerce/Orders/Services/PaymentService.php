<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Modules\Commerce\Exceptions\PaymentMismatchException;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Orders\Support\PaymentMetadataSanitizer;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Database\DatabaseManager;

/**
 * Owns completed payment records, order settlement projections, and till cash.
 */
final readonly class PaymentService
{
    public function __construct(
        private DatabaseManager $database,
        private PaymentMetadataSanitizer $metadata,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * Record one idempotent pending manual-payment preference for a web order.
     */
    public function recordPendingPreference(
        Order $order,
        PaymentMethod $method,
        ?User $actor = null,
    ): Payment {
        return $this->database->transaction(function () use ($order, $method, $actor): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->channel !== OrderChannel::Web
                || in_array($order->status, [OrderStatus::Held, OrderStatus::Cancelled], true)
                || $order->payment_status !== OrderPaymentStatus::Unpaid
                || $order->paid_minor !== 0) {
                throw new InvalidOrderTransitionException('Only unpaid active web orders may record a pending payment preference.');
            }

            $existing = Payment::query()
                ->whereBelongsTo($order)
                ->where('status', PaymentStatus::Pending->value)
                ->first();
            if ($existing instanceof Payment) {
                return $existing->load('order');
            }

            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $order->getKey(),
                'till_session_id' => null,
                'recorded_by' => $actor?->getKey(),
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $order->total_minor,
                'tendered_minor' => null,
                'change_minor' => 0,
                'reference' => null,
                'metadata' => null,
                'paid_at' => null,
            ])->save();

            $this->activities->record(
                activityType: 'commerce.payment.preference_recorded',
                description: "Payment preference recorded for order {$order->order_number}",
                actor: $actor,
                subject: $payment,
                properties: [
                    'order_ulid' => $order->ulid,
                    'method' => $method,
                    'amount_minor' => $order->total_minor,
                ],
                source: 'commerce-payments',
            );

            return $payment->refresh()->load('order');
        });
    }

    /**
     * Apply one completed payment to a locked order balance.
     */
    public function recordCompleted(
        Order $order,
        PaymentData $data,
        ?TillSession $session = null,
        ?User $actor = null,
    ): Payment {
        return $this->database->transaction(function () use ($order, $data, $session, $actor): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertPayable($order);
            $session = $this->resolveTill($order, $session);

            $paidBefore = (int) Payment::query()
                ->whereBelongsTo($order)
                ->where('status', PaymentStatus::Completed->value)
                ->sum('amount_minor');
            $balance = max(0, $order->total_minor - $paidBefore);

            if ($data->amountMinor > $balance) {
                throw new PaymentMismatchException('The payment amount exceeds the outstanding order balance.');
            }

            $tendered = $data->tenderedMinor ?? $data->amountMinor;
            if ($data->method === PaymentMethod::Cash && $tendered < $data->amountMinor) {
                throw new PaymentMismatchException('Cash tendered cannot be less than the applied amount.');
            }
            if ($data->method !== PaymentMethod::Cash && $tendered !== $data->amountMinor) {
                throw new PaymentMismatchException('Non-cash tender must equal the applied payment amount.');
            }

            $change = $data->method === PaymentMethod::Cash ? $tendered - $data->amountMinor : 0;
            $payment = Payment::query()
                ->whereBelongsTo($order)
                ->where('status', PaymentStatus::Pending->value)
                ->orderByRaw('CASE WHEN method = ? THEN 0 ELSE 1 END', [$data->method->value])
                ->oldest('id')
                ->first() ?? new Payment;
            $payment->forceFill([
                'order_id' => $order->getKey(),
                'till_session_id' => $session?->getKey(),
                'recorded_by' => $actor?->getKey(),
                'method' => $data->method,
                'status' => PaymentStatus::Completed,
                'amount_minor' => $data->amountMinor,
                'tendered_minor' => $tendered,
                'change_minor' => $change,
                'reference' => filled($data->reference) ? trim((string) $data->reference) : null,
                'metadata' => $this->metadata->sanitize($data->metadata) ?: null,
                'paid_at' => now(),
            ])->save();

            $paidAfter = $paidBefore + $data->amountMinor;
            $order->forceFill([
                'paid_minor' => $paidAfter,
                'payment_status' => $paidAfter >= $order->total_minor
                    ? OrderPaymentStatus::Paid
                    : OrderPaymentStatus::Partial,
            ])->save();

            if ($session !== null && $data->method === PaymentMethod::Cash) {
                $session->forceFill([
                    'expected_cash_minor' => $session->expected_cash_minor + $data->amountMinor,
                ])->save();
            }

            $this->activities->record(
                activityType: 'commerce.payment.completed',
                description: "Payment completed for order {$order->order_number}",
                actor: $actor,
                subject: $payment,
                properties: [
                    'order_ulid' => $order->ulid,
                    'method' => $data->method,
                    'amount_minor' => $data->amountMinor,
                    'change_minor' => $change,
                    'order_payment_status' => $order->payment_status,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-payments',
            );

            if ($order->channel === OrderChannel::Web) {
                OrderPaymentConfirmed::dispatch($order->ulid, $payment->ulid);
            }

            return $payment->refresh()->load(['order', 'tillSession']);
        });
    }

    private function assertPayable(Order $order): void
    {
        if (in_array($order->status, [OrderStatus::Held, OrderStatus::Cancelled], true)) {
            throw new InvalidOrderTransitionException('Held or cancelled orders cannot accept completed payments.');
        }

        if ($order->total_minor <= 0 || $order->paid_minor >= $order->total_minor) {
            throw new PaymentMismatchException('The order has no outstanding payable balance.');
        }
    }

    private function resolveTill(Order $order, ?TillSession $session): ?TillSession
    {
        if ($order->channel === OrderChannel::Web) {
            if ($session !== null) {
                throw new TillSessionException('Web-order payments cannot be assigned to a POS till.');
            }

            return null;
        }

        if ($session === null || $order->till_session_id !== $session->getKey()) {
            throw new TillSessionException('POS payments require the order\'s open till session.');
        }

        $session = TillSession::query()->lockForUpdate()->findOrFail($session->getKey());
        if (! $session->isOpen()) {
            throw new TillSessionException('Payments cannot be posted to a closed till session.');
        }

        return $session;
    }
}
