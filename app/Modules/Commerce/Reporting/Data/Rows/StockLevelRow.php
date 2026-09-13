<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data\Rows;

/**
 * Display-safe stock-level row for the inventory register.
 */
final readonly class StockLevelRow
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $category,
        public int $onHand,
        public int $threshold,
        public int $available,
        public string $state,
    ) {}
}
