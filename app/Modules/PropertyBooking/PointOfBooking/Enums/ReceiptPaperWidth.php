<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Enums;

/** Supported thermal receipt roll widths in millimetres. */
enum ReceiptPaperWidth: int
{
    case Roll58 = 58;
    case Roll80 = 80;

    /** Get the operator-facing label. */
    public function label(): string
    {
        return "{$this->value} mm roll";
    }

    /** Get the printable receipt width in millimetres. */
    public function printableWidth(): int
    {
        return $this->value - 8;
    }
}
