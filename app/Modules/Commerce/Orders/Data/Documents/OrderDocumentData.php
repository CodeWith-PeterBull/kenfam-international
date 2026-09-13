<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data\Documents;

use Carbon\CarbonImmutable;

/**
 * Stable document projection containing only customer-visible order values.
 */
final readonly class OrderDocumentData
{
    /**
     * @param  list<OrderDocumentLineData>  $lines
     */
    public function __construct(
        public string $orderNumber,
        public string $channelLabel,
        public string $statusLabel,
        public string $paymentStatusLabel,
        public string $fulfillmentLabel,
        public string $customerName,
        public ?CarbonImmutable $placedAt,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $deliveryFeeMinor,
        public int $taxMinor,
        public int $totalMinor,
        public bool $taxInclusive,
        public array $lines,
    ) {}

    /**
     * Return the sum of persisted whole-unit line quantities.
     */
    public function itemQuantity(): int
    {
        return array_sum(array_map(
            static fn (OrderDocumentLineData $line): int => $line->quantity,
            $this->lines,
        ));
    }
}
