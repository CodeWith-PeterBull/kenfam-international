<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Stores the aggregate settlement state derived from completed payments.
 */
enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    /**
     * Return the human-readable payment state.
     */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
