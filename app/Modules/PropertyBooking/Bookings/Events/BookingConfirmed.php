<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that an expected booking was explicitly confirmed. */
final readonly class BookingConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit confirmation payload. */
    public function __construct(public string $bookingUlid, public int $propertyId) {}
}
