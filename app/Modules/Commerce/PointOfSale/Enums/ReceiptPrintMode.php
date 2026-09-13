<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Enums;

/**
 * Controls whether checkout opens the browser print dialog automatically.
 */
enum ReceiptPrintMode: string
{
    case Manual = 'manual';
    case AutoPrompt = 'auto_prompt';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Print on request',
            self::AutoPrompt => 'Prompt after each sale',
        };
    }
}
