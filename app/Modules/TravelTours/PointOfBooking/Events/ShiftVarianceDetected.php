<?php

/** Emitted after a closed shift's variance crosses the review threshold. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Scalar-only payload so the queued notification re-reads the shift at delivery time. */
final readonly class ShiftVarianceDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** Carry the closed shift's identity and the figures that tripped the threshold. */
    public function __construct(
        public string $shiftUlid,
        public int $varianceMinor,
        public int $thresholdMinor,
    ) {}
}
