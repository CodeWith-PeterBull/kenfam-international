<?php

/** Emitted after an operator confirms a booking and the transaction commits. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Events;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a booking transitions to confirmed. */
final class TourBookingConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** Carry the confirmed booking. */
    public function __construct(public readonly TourBooking $booking) {}
}
