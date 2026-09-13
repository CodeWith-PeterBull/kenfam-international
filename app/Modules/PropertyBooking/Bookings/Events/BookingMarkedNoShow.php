<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that an overdue confirmed arrival was reviewed as a no-show. */
final readonly class BookingMarkedNoShow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit no-show payload. */
    public function __construct(public string $bookingUlid, public int $propertyId) {}
}
