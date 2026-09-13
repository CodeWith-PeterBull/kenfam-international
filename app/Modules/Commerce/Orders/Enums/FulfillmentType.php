<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Describes how an order reaches its customer.
 */
enum FulfillmentType: string
{
    case Counter = 'counter';
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    /**
     * Return the customer-facing fulfillment label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Counter => 'Counter sale',
            self::Pickup => 'Store pickup',
            self::Delivery => 'Delivery',
        };
    }
}
