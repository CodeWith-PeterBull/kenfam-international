<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Represents the unified web-order and POS-sale lifecycle.
 */
enum OrderStatus: string
{
    case Held = 'held';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Return the human-readable status label.
     */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /**
     * Return whether the state ends the initial order lifecycle.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
