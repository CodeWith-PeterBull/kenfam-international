<?php

/** Scheduled sweep that frees capacity held by expired availability holds. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Console\Commands;

use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use Illuminate\Console\Command;

/** Thin console entry over AvailabilityHoldService::releaseExpired(). */
final class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'travel-tours:release-expired-holds';

    protected $description = 'Mark expired availability holds as released so their seats can be sold again.';

    /** Run the sweep and report how many holds were released. */
    public function handle(AvailabilityHoldService $holds): int
    {
        $released = $holds->releaseExpired();
        $this->info("Released {$released} expired ".($released === 1 ? 'hold' : 'holds').'.');

        return self::SUCCESS;
    }
}
