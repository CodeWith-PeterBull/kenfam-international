<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Printing\Data;

use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;

/**
 * Scalar register-owned receipt printer preferences passed to a driver.
 */
final readonly class ReceiptPrinterSettingsData
{
    public function __construct(
        public string $driver,
        public ReceiptPrintMode $mode,
        public ReceiptPaperWidth $paperWidth,
        public ?string $printerName,
    ) {}
}
