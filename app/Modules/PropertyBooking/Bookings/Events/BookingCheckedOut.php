<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that one in-house booking completed checkout. */
final readonly class BookingCheckedOut implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit departure payload. */
    public function __construct(public string $bookingUlid, public int $propertyId) {}
}
