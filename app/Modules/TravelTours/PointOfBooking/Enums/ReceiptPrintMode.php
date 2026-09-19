<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Enums;

/**
 * Operator interaction after a completed desk booking. The register stores
 * this as its `automatic_receipt_print` flag; the enum gives the flag a
 * label and a stable value for forms and configuration.
 */
enum ReceiptPrintMode: string
{
    case Manual = 'manual';
    case AutoPrompt = 'auto_prompt';

    /** Resolve the mode a register's automatic-print flag represents. */
    public static function fromAutomatic(bool $automatic): self
    {
        return $automatic ? self::AutoPrompt : self::Manual;
    }

    /** Get the operator-facing label. */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::AutoPrompt => 'Auto prompt',
        };
    }

    /** Whether completed sales should open the print dialog without a click. */
    public function isAutomatic(): bool
    {
        return $this === self::AutoPrompt;
    }
}
