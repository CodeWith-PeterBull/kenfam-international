<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Printing;

use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Contracts\ReceiptPrinterDriver;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\PropertyBooking\PointOfBooking\Printing\Data\ReceiptPrintInstructionData;
use Illuminate\Contracts\Container\Container;
use LogicException;

/** Resolves the register-selected POB print adapter from module configuration. */
final readonly class ReceiptPrinterManager
{
    /** Create the printer manager with the application container. */
    public function __construct(private Container $container) {}

    /** Resolve settings and produce one browser-safe instruction. */
    public function instruction(
        ?ReceptionRegister $register,
        BookingReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData {
        $settings = $this->settings($register);
        $driverClass = config("property-booking.pob.receipt_printing.drivers.{$settings->driver}");
        if (! is_string($driverClass) || $driverClass === '') {
            throw new LogicException("Receipt printer driver [{$settings->driver}] is not configured.");
        }
        $driver = $this->container->make($driverClass);
        if (! $driver instanceof ReceiptPrinterDriver) {
            throw new LogicException("Receipt printer driver [{$settings->driver}] must implement ReceiptPrinterDriver.");
        }

        return $driver->instruction($settings, $receipt, $afterCheckout);
    }

    /** Resolve register overrides on top of bounded module defaults. */
    private function settings(?ReceptionRegister $register): ReceiptPrinterSettingsData
    {
        $defaultMode = ReceiptPrintMode::tryFrom((string) config('property-booking.pob.receipt_printing.default_mode', 'manual'))
            ?? ReceiptPrintMode::Manual;
        $defaultWidth = ReceiptPaperWidth::tryFrom((int) config('property-booking.pob.receipt_printing.default_paper_width_mm', 80))
            ?? ReceiptPaperWidth::Roll80;

        return new ReceiptPrinterSettingsData(
            driver: $register?->receipt_print_driver ?: (string) config('property-booking.pob.receipt_printing.default_driver', 'browser'),
            mode: $register?->receipt_print_mode ?? $defaultMode,
            paperWidth: $register?->receipt_paper_width ?? $defaultWidth,
            printerName: filled($register?->receipt_printer_name) ? trim((string) $register?->receipt_printer_name) : null,
        );
    }
}
