<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Printing\Drivers;

use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Contracts\ReceiptPrinterDriver;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrintInstructionData;

/** Uses the standards-based browser print dialog and never claims silent output. */
final readonly class BrowserReceiptPrinterDriver implements ReceiptPrinterDriver
{
    /** Produce a manual or post-checkout browser-dialog instruction. */
    public function instruction(
        ReceiptPrinterSettingsData $settings,
        BookingReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData {
        return new ReceiptPrintInstructionData(
            driver: $settings->driver,
            strategy: 'browser-dialog',
            mode: $settings->mode,
            paperWidth: $settings->paperWidth,
            printerName: $settings->printerName,
            autoPrompt: $afterCheckout && $settings->mode === ReceiptPrintMode::AutoPrompt,
            supportsSilentPrinting: false,
            receiptKey: $receipt->bookingNumber,
        );
    }
}
