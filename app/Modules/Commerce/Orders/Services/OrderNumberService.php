<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Modules\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Models\Order;

/**
 * Assigns concurrency-safe business numbers derived from persisted integer keys.
 */
final class OrderNumberService
{
    /**
     * Assign a stable number exactly once inside the caller's transaction.
     */
    public function assign(Order $order): string
    {
        if ($order->order_number !== null) {
            return $order->order_number;
        }

        if (! $order->exists || $order->getKey() === null) {
            throw new InvalidOrderTransitionException('An order must be persisted before numbering.');
        }

        $prefixKey = $order->channel === OrderChannel::PointOfSale ? 'pos_prefix' : 'web_prefix';
        $prefix = strtoupper((string) config("commerce.numbering.{$prefixKey}"));

        if (preg_match('/^[A-Z0-9]{2,8}$/', $prefix) !== 1) {
            throw new InvalidOrderTransitionException('The configured order-number prefix is invalid.');
        }

        $date = ($order->created_at ?? now())->format('Ymd');
        $number = sprintf('%s-%s-%08d', $prefix, $date, $order->getKey());
        $order->forceFill(['order_number' => $number])->save();

        return $number;
    }
}
