<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that an expected booking was explicitly cancelled. */
final readonly class BookingCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit cancellation payload. */
    public function __construct(public string $bookingUlid, public int $propertyId) {}
}
