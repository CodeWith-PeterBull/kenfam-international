<?php

/**
 * Verifies the K1 runtime closeout boundary as it stands after the booking desk shipped.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Modules\TravelTours\Contracts\PrintsBookingReceipts;
use App\Modules\TravelTours\PointOfBooking\Printing\BrowserReceiptPrinterDriver;
use Tests\TestCase;

/** The receipt contract is bound only to a driver that does real work. */
final class TravelToursFoundationCloseoutTest extends TestCase
{
    /** K1 refused a log-only stub; M6 binds the browser driver, which hands the print to the terminal. */
    public function test_receipt_printing_contract_is_bound_to_the_browser_driver(): void
    {
        $this->assertTrue(app()->bound(PrintsBookingReceipts::class));
        $this->assertInstanceOf(BrowserReceiptPrinterDriver::class, app(PrintsBookingReceipts::class));
    }
}
