<?php

/** Emitted after payment evidence is recorded and awaits confirmation. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Events;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted once a pending payment row has been committed. */
final class BookingPaymentRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** Carry the pending payment. */
    public function __construct(public readonly BookingPayment $payment) {}
}
