<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Printing\Data;

use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;

/** Scalar register-owned receipt printer settings supplied to a driver. */
final readonly class ReceiptPrinterSettingsData
{
    /** Create normalized driver preferences. */
    public function __construct(
        public string $driver,
        public ReceiptPrintMode $mode,
        public ReceiptPaperWidth $paperWidth,
        public ?string $printerName,
    ) {}
}
