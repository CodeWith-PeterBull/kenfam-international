<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrinterSettingsData;
use App\Modules\TravelTours\PointOfBooking\Data\ReceiptPrintInstructionData;

/** Receipt-printer adapter boundary for the browser driver and future thermal drivers. */
interface PrintsBookingReceipts
{
    /** Describe how the terminal should put this receipt in the operator's hands. */
    public function instruction(ReceiptPrinterSettingsData $settings, BookingReceiptData $receipt, bool $afterCheckout): ReceiptPrintInstructionData;
}
