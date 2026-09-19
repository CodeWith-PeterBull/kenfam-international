<?php

/** Prints receipts through the operator's browser print dialog. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Printing;

use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrinterSettingsData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;

/**
 * The browser driver has no device to talk to: the receipt page carries the
 * instruction and the page script opens the standard print dialog, once
 * after checkout when the register asks for it. It never claims silent output.
 */
final readonly class BrowserReceiptPrinterDriver implements PrintsBookingReceipts
{
    public const NAME = 'browser';

    /** Produce a manual or post-checkout browser-dialog instruction. */
    public function instruction(ReceiptPrinterSettingsData $settings, BookingReceiptData $receipt, bool $afterCheckout): ReceiptPrintInstructionData
    {
        return new ReceiptPrintInstructionData(
            driver: $settings->driver,
            strategy: 'browser-dialog',
            mode: $settings->mode,
            paperWidth: $settings->paperWidth,
            printerName: $settings->printerName,
            autoPrompt: $afterCheckout && $settings->mode->isAutomatic(),
            supportsSilentPrinting: false,
            receiptKey: $receipt->bookingNumber,
        );
    }
}
