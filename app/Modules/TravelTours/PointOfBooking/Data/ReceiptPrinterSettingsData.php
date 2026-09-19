<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPrintMode;

/** The register's receipt-printer configuration resolved over the module defaults. */
final readonly class ReceiptPrinterSettingsData
{
    /** Create the settings a driver turns into a browser-safe instruction. */
    public function __construct(
        public string $driver,
        public ReceiptPrintMode $mode,
        public ReceiptPaperWidth $paperWidth,
        public ?string $printerName,
    ) {}
}
