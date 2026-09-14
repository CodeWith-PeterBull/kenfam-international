<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use Illuminate\Support\Facades\Log;

/** Browser-print adapter marker; the authenticated UI performs the print invocation. */
final class BrowserReceiptPrinter implements PrintsBookingReceipts
{
    /** Execute the print operation within the owning TravelTours boundary. */
    public function print(BookingPayment $payment, BookingRegister $register): void
    {
        Log::info('Travel receipt queued for browser print.', ['payment_ulid' => $payment->ulid, 'register_ulid' => $register->ulid, 'driver' => $register->receipt_printer_driver]);
    }
}
