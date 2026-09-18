<?php

/** Validated operator input for a dated tour departure. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Data;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;

/** Keep transport fields separate from database-owned status and attribution. */
final readonly class DepartureData
{
    /** Carry the complete editable schedule without implicit model filling. */
    public function __construct(
        public int $tourId,
        public string $code,
        public string $timezone,
        public string $localStart,
        public string $localEnd,
        public ?string $localBookingOpen,
        public ?string $localBookingClose,
        public int $capacity,
        public int $minimumParticipants,
        public ?int $ratePlanId,
        public ?BookingMode $bookingMode,
        public ?string $meetingInstructions,
        public ?string $operationalNotes,
    ) {}
}
