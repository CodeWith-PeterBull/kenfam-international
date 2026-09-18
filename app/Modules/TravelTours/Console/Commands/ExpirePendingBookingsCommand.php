<?php

/** Scheduled sweep that expires unpaid pending bookings past their approval window. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Console\Commands;

use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use Illuminate\Console\Command;

/** Thin console entry over BookingLifecycleService::expirePending(). */
final class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'travel-tours:expire-pending-bookings';

    protected $description = 'Expire pending bookings whose approval window closed without any recorded payment.';

    /** Run the sweep and report how many bookings were expired. */
    public function handle(BookingLifecycleService $bookings): int
    {
        $expired = $bookings->expirePending();
        $this->info("Expired {$expired} pending ".($expired === 1 ? 'booking' : 'bookings').'.');

        return self::SUCCESS;
    }
}
