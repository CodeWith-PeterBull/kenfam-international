<?php

/**
 * Carries committed TravelTours domain state to event listeners.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Events;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted once a payment has been recorded and booking totals are synchronized. */
final class BookingPaymentConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** Initialize the BookingPaymentConfirmed with its required dependencies or immutable state. */
    public function __construct(public readonly BookingPayment $payment) {}
}
