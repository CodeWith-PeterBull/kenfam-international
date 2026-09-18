<?php

/**
 * Resolves the receipt printer driver configured for a register.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Printing\BrowserReceiptPrinterDriver;

/**
 * Only the browser driver exists today; a thermal driver would implement
 * PrintsBookingReceipts and be added to the map here without touching the
 * terminal, which only consumes the instruction.
 */
final readonly class ReceiptPrinterManager
{
    /** Inject the drivers the module ships with. */
    public function __construct(private BrowserReceiptPrinterDriver $browser) {}

    /** Produce the instruction for one payment on the register that took it; printing never throws into the sale. */
    public function instructionFor(BookingPayment $payment, BookingRegister $register): ReceiptPrintInstructionData
    {
        $driver = (string) ($register->receipt_printer_driver ?: config('travel-tours.pob.receipt_printing.default_driver', BrowserReceiptPrinterDriver::NAME));

        return match ($driver) {
            BrowserReceiptPrinterDriver::NAME => $this->browser->instruction($payment, $register),
            default => throw new PointOfBookingException("No receipt printer driver named {$driver} is installed."),
        };
    }
}
