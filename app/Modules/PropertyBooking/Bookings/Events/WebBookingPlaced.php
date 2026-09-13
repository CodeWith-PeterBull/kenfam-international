<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that one self-service booking committed with an exact allocation. */
final readonly class WebBookingPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create the scalar-only after-commit event payload. */
    public function __construct(public string $bookingUlid, public int $propertyId = 0) {}
}
