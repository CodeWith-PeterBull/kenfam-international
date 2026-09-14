<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Services;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\ChecksDepartureAvailability;
use App\Modules\TravelTours\Scheduling\Data\DepartureAvailability;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;

/** Calculates authoritative availability from active bookings and unexpired holds. */
final class DepartureAvailabilityService implements ChecksDepartureAvailability
{
    /** Project current departure availability without mutating capacity records. */
    public function check(TourDeparture $departure, int $requestedSeats = 1): DepartureAvailability
    {
        $booked = (int) TourBooking::query()->where('departure_id', $departure->getKey())->capacityConsuming()->sum('seat_count');
        $held = (int) $departure->holds()->where('status', HoldStatus::Active->value)->where('expires_at', '>', now())->sum('seat_count');
        $available = max((int) $departure->capacity - $booked - $held, 0);

        return new DepartureAvailability((int) $departure->capacity, $booked, $held, $available, $requestedSeats > 0 && $available >= $requestedSeats);
    }
}
