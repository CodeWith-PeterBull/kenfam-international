<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Printing\Contracts;

use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrintInstructionData;

/**
 * Produces a client instruction for one configured receipt printing strategy.
 */
interface ReceiptPrinterDriver
{
    public function instruction(
        ReceiptPrinterSettingsData $settings,
        PosReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData;
}
