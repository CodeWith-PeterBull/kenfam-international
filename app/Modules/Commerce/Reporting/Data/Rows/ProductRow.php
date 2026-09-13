<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data\Rows;

/**
 * Display-safe product register row. Carries selling prices only; supplier cost
 * price and internal identifiers are excluded by construction.
 */
final readonly class ProductRow
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $category,
        public string $status,
        public string $listPrice,
        public ?string $salePrice,
        public string $sellingPrice,
        public bool $featured,
        public string $stock,
    ) {}
}
