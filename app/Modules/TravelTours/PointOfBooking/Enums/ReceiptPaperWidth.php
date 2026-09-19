<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Enums;

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

    /** Get the printable receipt width in millimetres once the roll margins are taken off. */
    public function printableWidth(): int
    {
        return $this->value - 8;
    }
}
