<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that every occupant in one booking was checked in. */
final readonly class BookingCheckedIn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit arrival payload. */
    public function __construct(public string $bookingUlid, public int $propertyId) {}
}
