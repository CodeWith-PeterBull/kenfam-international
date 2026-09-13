<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

/**
 * Complete integer-money result persisted by an order transaction.
 */
final readonly class CartCalculation
{
    /**
     * @param  list<CalculatedCartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $currency,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $deliveryFeeMinor,
        public int $taxMinor,
        public int $totalMinor,
        public bool $taxInclusive,
    ) {}
}
