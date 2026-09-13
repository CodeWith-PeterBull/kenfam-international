<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Enums;

/** Operator interaction after a successful booking payment. */
enum ReceiptPrintMode: string
{
    case Manual = 'manual';
    case AutoPrompt = 'auto_prompt';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
