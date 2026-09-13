<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data\Documents;

/**
 * Privacy-safe immutable snapshot of one line rendered on an order document.
 */
final readonly class OrderDocumentLineData
{
    public function __construct(
        public string $productName,
        public string $sku,
        public int $quantity,
        public int $unitPriceMinor,
        public int $taxMinor,
        public int $lineTotalMinor,
    ) {}
}
