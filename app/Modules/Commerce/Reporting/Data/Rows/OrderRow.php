<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data\Rows;

/**
 * Display-safe order register row. Carries the business order number and
 * customer-facing selling totals only; internal identifiers are excluded.
 */
final readonly class OrderRow
{
    public function __construct(
        public string $orderNumber,
        public string $placedAt,
        public string $customer,
        public string $channel,
        public string $status,
        public string $paymentStatus,
        public int $itemCount,
        public string $total,
        public string $paid,
        public string $balance,
    ) {}
}
