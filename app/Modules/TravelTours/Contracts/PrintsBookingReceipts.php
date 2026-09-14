<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;

/** Receipt-printer adapter boundary for browser and future thermal drivers. */
interface PrintsBookingReceipts
{
    /** Execute the print operation within the owning TravelTours boundary. */
    public function print(BookingPayment $payment, BookingRegister $register): void;
}
