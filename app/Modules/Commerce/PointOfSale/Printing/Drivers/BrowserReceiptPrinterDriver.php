<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Printing\Drivers;

use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\PointOfSale\Printing\Contracts\ReceiptPrinterDriver;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrintInstructionData;

/**
 * Uses the standards-based browser dialog and never claims silent printing.
 */
final readonly class BrowserReceiptPrinterDriver implements ReceiptPrinterDriver
{
    public function instruction(
        ReceiptPrinterSettingsData $settings,
        PosReceiptData $receipt,
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
            receiptKey: $receipt->orderNumber,
        );
    }
}
