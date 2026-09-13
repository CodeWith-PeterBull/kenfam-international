<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Represents the lifecycle of one payment or tender record.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Voided = 'voided';
    case Refunded = 'refunded';

    /**
     * Return the human-readable status label.
     */
    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
