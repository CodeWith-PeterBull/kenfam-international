<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;

/** Adapter-ready booking payment recording boundary. */
interface ProcessesBookingPayments
{
    /** Record or return the one payment identified by the operation key. */
    public function record(TourBooking $booking, BookingPaymentData $data): BookingPayment;
}
