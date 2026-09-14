<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Models\TourBooking;

/** Atomic booking placement boundary. */
interface PlacesTourBookings
{
    /** Atomically place a quote-verified booking and return its persisted aggregate. */
    public function place(BookingPlacementData $data): TourBooking;
}
