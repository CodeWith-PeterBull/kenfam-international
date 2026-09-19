<?php

/**
 * Resolves the receipt printer driver configured for a register.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrinterSettingsData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\TravelTours\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use Illuminate\Contracts\Container\Container;

/**
 * Drivers are named in `travel-tours.pob.receipt_printing.drivers`; a thermal
 * driver would implement PrintsBookingReceipts and be added to that map
 * without touching the terminal, which only consumes the instruction.
 */
final readonly class ReceiptPrinterManager
{
    /** Create the printer manager with the application container. */
    public function __construct(private Container $container) {}

    /** Resolve the register's settings and produce one browser-safe instruction. */
    public function instruction(?BookingRegister $register, BookingReceiptData $receipt, bool $afterCheckout): ReceiptPrintInstructionData
    {
        $settings = $this->settings($register);
        $driverClass = config("travel-tours.pob.receipt_printing.drivers.{$settings->driver}");
        if (! is_string($driverClass) || $driverClass === '') {
            throw new PointOfBookingException("No receipt printer driver named {$settings->driver} is installed.");
        }
        $driver = $this->container->make($driverClass);
        if (! $driver instanceof PrintsBookingReceipts) {
            throw new PointOfBookingException("Receipt printer driver {$settings->driver} must implement PrintsBookingReceipts.");
        }

        return $driver->instruction($settings, $receipt, $afterCheckout);
    }

    /** Resolve register overrides on top of bounded module defaults. */
    private function settings(?BookingRegister $register): ReceiptPrinterSettingsData
    {
        $defaultMode = ReceiptPrintMode::tryFrom((string) config('travel-tours.pob.receipt_printing.default_mode', 'manual')) ?? ReceiptPrintMode::Manual;
        $defaultWidth = ReceiptPaperWidth::tryFrom((int) config('travel-tours.pob.receipt_printing.default_paper_width_mm', 80)) ?? ReceiptPaperWidth::Roll80;

        return new ReceiptPrinterSettingsData(
            driver: $register?->receipt_printer_driver ?: (string) config('travel-tours.pob.receipt_printing.default_driver', 'browser'),
            mode: $register instanceof BookingRegister ? ReceiptPrintMode::fromAutomatic((bool) $register->automatic_receipt_print) : $defaultMode,
            paperWidth: ($register instanceof BookingRegister ? ReceiptPaperWidth::tryFrom((int) $register->receipt_paper_width_mm) : null) ?? $defaultWidth,
            printerName: filled($register?->receipt_printer_name) ? trim((string) $register?->receipt_printer_name) : null,
        );
    }
}
