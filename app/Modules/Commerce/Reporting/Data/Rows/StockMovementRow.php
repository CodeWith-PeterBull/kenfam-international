<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data\Rows;

/**
 * Display-safe stock-ledger row for the movement history register.
 */
final readonly class StockMovementRow
{
    public function __construct(
        public string $occurredAt,
        public string $product,
        public string $sku,
        public string $type,
        public string $quantityDelta,
        public int $balanceAfter,
        public string $reference,
        public string $actor,
    ) {}
}
