<?php

/** Prints receipts through the operator's browser print dialog. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Printing;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;

/**
 * The browser driver has no device to talk to: the print itself happens in
 * the terminal, which opens the receipt page and (when the register asks for
 * it) triggers the print dialog once. Printing never rolls back a sale.
 */
final class BrowserReceiptPrinterDriver implements PrintsBookingReceipts
{
    public const NAME = 'browser';

    /** Nothing to send server-side; the instruction below carries the work to the terminal. */
    public function print(BookingPayment $payment, BookingRegister $register): void {}

    /** Describe the receipt page the terminal should open for this payment. */
    public function instruction(BookingPayment $payment, BookingRegister $register): ReceiptPrintInstructionData
    {
        $automatic = (bool) $register->automatic_receipt_print
            || ((string) config('travel-tours.pob.receipt_printing.default_mode', 'manual')) === 'automatic';
        $width = (int) ($register->receipt_paper_width_mm ?: config('travel-tours.pob.receipt_printing.default_paper_width_mm', 80));

        return new ReceiptPrintInstructionData(
            driver: self::NAME,
            url: route('travel-tours.pob.receipt', ['payment' => $payment->ulid, 'auto' => $automatic ? 1 : 0]),
            automatic: $automatic,
            paperWidthMm: in_array($width, [58, 80], true) ? $width : 80,
        );
    }
}
