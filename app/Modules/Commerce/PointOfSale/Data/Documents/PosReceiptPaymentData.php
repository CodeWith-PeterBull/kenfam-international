<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Data\Documents;

/**
 * Immutable public tender snapshot that intentionally excludes raw metadata.
 */
final readonly class PosReceiptPaymentData
{
    public function __construct(
        public string $methodLabel,
        public ?string $reference,
        public int $amountMinor,
        public int $changeMinor,
    ) {}
}
