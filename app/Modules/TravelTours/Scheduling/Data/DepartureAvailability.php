<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Data;

/** Capacity result with committed, held, and remaining seat evidence. */
final readonly class DepartureAvailability
{
    /** Initialize the DepartureAvailability with its required dependencies or immutable state. */
    public function __construct(
        public int $capacity,
        public int $bookedSeats,
        public int $heldSeats,
        public int $availableSeats,
        public bool $canAccommodate,
    ) {}
}
