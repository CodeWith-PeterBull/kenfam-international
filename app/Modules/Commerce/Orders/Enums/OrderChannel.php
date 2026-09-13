<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Enums;

/**
 * Identifies the transaction surface that originated an order.
 */
enum OrderChannel: string
{
    case Web = 'web';
    case PointOfSale = 'pos';

    /**
     * Return the channel label used in administration.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Online store',
            self::PointOfSale => 'Point of sale',
        };
    }
}
