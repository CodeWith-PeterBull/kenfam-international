<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a confirmed operational booking detail changed. */
final readonly class BookingModified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit modification payload. */
    public function __construct(
        public string $bookingUlid,
        public int $propertyId,
        public string $changeType,
    ) {}
}
