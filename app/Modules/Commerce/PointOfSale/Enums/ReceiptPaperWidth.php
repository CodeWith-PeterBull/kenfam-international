<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Enums;

/**
 * Supported thermal-roll layout widths in millimetres.
 */
enum ReceiptPaperWidth: int
{
    case Roll58 = 58;
    case Roll80 = 80;

    public function label(): string
    {
        return "{$this->value} mm roll";
    }

    public function printableWidth(): int
    {
        return $this->value - 8;
    }
}
