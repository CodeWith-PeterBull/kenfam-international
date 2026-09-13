<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Enums;

/**
 * Indicates whether a register session may accept POS transactions.
 */
enum TillSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * Return the administration label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
