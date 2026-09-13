<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals the first transition from healthy stock into a positive low balance. */
final readonly class StockBecameLow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $productUlid,
        public int $balanceBefore,
        public int $balanceAfter,
        public int $threshold,
    ) {}
}
