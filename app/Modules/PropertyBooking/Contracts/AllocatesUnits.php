<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Contracts;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;

/** Commits and releases exact physical-unit allocations transactionally. */
interface AllocatesUnits
{
    /** Allocate exact concrete units to the requested stay. */
    public function allocate(BookingStay $stay, ?User $actor = null, ?int $preferredUnitId = null): UnitAssignment;

    /** Release the active domain record transactionally. */
    public function release(UnitAssignment $assignment, string $reason, ?User $actor = null): UnitAssignment;
}
