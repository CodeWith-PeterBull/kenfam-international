<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Scheduling\Data\DepartureAvailability;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;

/** Departure capacity query boundary. */
interface ChecksDepartureAvailability
{
    /** Project current departure availability without mutating capacity records. */
    public function check(TourDeparture $departure, int $requestedSeats = 1): DepartureAvailability;
}
