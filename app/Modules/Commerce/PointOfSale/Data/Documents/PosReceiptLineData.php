<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Data\Documents;

/**
 * Immutable selling-price snapshot for one public receipt line.
 */
final readonly class PosReceiptLineData
{
    public function __construct(
        public string $productName,
        public string $sku,
        public int $quantity,
        public int $unitPriceMinor,
        public int $lineTotalMinor,
    ) {}
}
