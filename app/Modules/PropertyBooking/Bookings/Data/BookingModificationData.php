<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Data;

use Carbon\CarbonImmutable;

/** Typed, non-authoritative operator request for one booking interval change. */
final readonly class BookingModificationData
{
    /** Create a bounded interval-modification request. */
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $reason,
    ) {}
}
