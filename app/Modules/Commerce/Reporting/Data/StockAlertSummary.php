<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

/**
 * Current low or depleted stock projection rendered by the dashboard.
 */
final readonly class StockAlertSummary
{
    public function __construct(
        public string $productUlid,
        public string $productName,
        public string $sku,
        public int $onHand,
        public int $lowStockThreshold,
        public string $state,
    ) {}
}
