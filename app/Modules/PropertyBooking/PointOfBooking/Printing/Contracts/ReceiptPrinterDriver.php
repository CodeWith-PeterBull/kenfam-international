<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Printing\Contracts;

use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrintInstructionData;

/** Contract for module-owned receipt printer adapters. */
interface ReceiptPrinterDriver
{
    /** Produce a client-safe instruction without performing an implicit side effect. */
    public function instruction(
        ReceiptPrinterSettingsData $settings,
        BookingReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData;
}
