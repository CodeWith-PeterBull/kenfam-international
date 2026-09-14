<?php

/**
 * Carries committed TravelTours domain state to event listeners.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Events;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a new tour booking transaction commits successfully. */
final class TourBookingPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Initialize the TourBookingPlaced with its required dependencies or immutable state. */
    public function __construct(public readonly TourBooking $booking) {}
}
