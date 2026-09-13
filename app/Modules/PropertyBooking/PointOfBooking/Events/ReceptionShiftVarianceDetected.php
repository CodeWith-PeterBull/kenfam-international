<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a reconciled reception shift crossed its variance threshold. */
final readonly class ReceptionShiftVarianceDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Create a scalar-only event payload safe for queued listeners. */
    public function __construct(
        public string $shiftUlid,
        public int $varianceMinor,
        public int $thresholdMinor,
        public int $propertyId = 0,
    ) {}
}
