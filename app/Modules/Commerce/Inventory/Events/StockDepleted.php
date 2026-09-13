<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals the transition from a positive stock balance to zero or below. */
final readonly class StockDepleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $productUlid,
        public int $balanceBefore,
        public int $balanceAfter,
    ) {}
}
