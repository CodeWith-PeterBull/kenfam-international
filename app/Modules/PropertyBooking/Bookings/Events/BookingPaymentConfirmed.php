<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a completed administration payment changed booking settlement. */
final readonly class BookingPaymentConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only after-commit payment payload. */
    public function __construct(
        public string $bookingUlid,
        public string $paymentUlid,
        public int $propertyId,
    ) {}
}
