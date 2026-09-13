<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Exceptions;

use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Models\Order;

/**
 * Reports an unsupported order lifecycle transition.
 */
final class InvalidOrderTransitionException extends CommerceException
{
    /**
     * Build a transition failure using external order identity only.
     */
    public static function forOrder(Order $order, OrderStatus $target): self
    {
        return new self(sprintf(
            'Order %s cannot move from %s to %s.',
            $order->order_number ?? $order->ulid,
            $order->status->value,
            $target->value,
        ));
    }
}
