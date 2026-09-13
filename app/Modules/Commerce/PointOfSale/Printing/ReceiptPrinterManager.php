<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Printing;

use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Printing\Contracts\ReceiptPrinterDriver;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrintInstructionData;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Resolves the register-selected print driver through module configuration.
 */
final readonly class ReceiptPrinterManager
{
    public function __construct(private Container $container) {}

    public function instruction(
        ?Register $register,
        PosReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData {
        $settings = $this->settings($register);
        $driverClass = config("commerce.pos.receipt_printing.drivers.{$settings->driver}");
        if (! is_string($driverClass) || $driverClass === '') {
            throw new LogicException("Receipt printer driver [{$settings->driver}] is not configured.");
        }

        $driver = $this->container->make($driverClass);
        if (! $driver instanceof ReceiptPrinterDriver) {
            throw new LogicException("Receipt printer driver [{$settings->driver}] must implement ReceiptPrinterDriver.");
        }

        return $driver->instruction($settings, $receipt, $afterCheckout);
    }

    private function settings(?Register $register): ReceiptPrinterSettingsData
    {
        $defaultDriver = (string) config('commerce.pos.receipt_printing.default_driver', 'browser');
        $defaultMode = ReceiptPrintMode::tryFrom((string) config('commerce.pos.receipt_printing.default_mode', 'manual'))
            ?? ReceiptPrintMode::Manual;
        $defaultWidth = ReceiptPaperWidth::tryFrom((int) config('commerce.pos.receipt_printing.default_paper_width_mm', 80))
            ?? ReceiptPaperWidth::Roll80;

        return new ReceiptPrinterSettingsData(
            driver: $register?->receipt_print_driver ?: $defaultDriver,
            mode: $register?->receipt_print_mode ?? $defaultMode,
            paperWidth: $register?->receipt_paper_width ?? $defaultWidth,
            printerName: filled($register?->receipt_printer_name)
                ? trim((string) $register?->receipt_printer_name)
                : null,
        );
    }
}
