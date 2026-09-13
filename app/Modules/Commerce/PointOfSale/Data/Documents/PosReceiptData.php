<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Data\Documents;

use Carbon\CarbonImmutable;

/**
 * Stable POS receipt projection shared by screen, browser print, and PDF views.
 */
final readonly class PosReceiptData
{
    /**
     * @param  list<PosReceiptLineData>  $lines
     * @param  list<PosReceiptPaymentData>  $payments
     */
    public function __construct(
        public string $orderNumber,
        public ?CarbonImmutable $placedAt,
        public string $registerName,
        public string $registerCode,
        public string $cashierName,
        public string $customerName,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public int $totalMinor,
        public bool $taxInclusive,
        public array $lines,
        public array $payments,
    ) {}

    /**
     * Return the sum of whole-unit line quantities shown on the receipt.
     */
    public function itemQuantity(): int
    {
        return array_sum(array_map(
            static fn (PosReceiptLineData $line): int => $line->quantity,
            $this->lines,
        ));
    }

    /**
     * Return the number of independently recorded payment tenders.
     */
    public function tenderCount(): int
    {
        return count($this->payments);
    }
}
