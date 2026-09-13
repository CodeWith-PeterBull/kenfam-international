<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a closed till exceeded the configured absolute variance threshold. */
final readonly class TillVarianceDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $tillSessionUlid,
        public int $varianceMinor,
        public int $thresholdMinor,
    ) {}
}
