<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

use App\Modules\Commerce\Catalog\Models\Product;

/**
 * Server-calculated immutable product and price snapshot for one order line.
 */
final readonly class CalculatedCartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public int $unitPriceMinor,
        public ?int $unitCostMinor,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxRateBps,
        public bool $isTaxInclusive,
        public int $taxMinor,
        public int $totalMinor,
    ) {}
}
