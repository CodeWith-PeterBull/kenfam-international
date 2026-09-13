<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * Safe read-only order projection rendered by the Commerce dashboard.
 */
final readonly class RecentOrderSummary
{
    public function __construct(
        public string $ulid,
        public string $orderNumber,
        public OrderChannel $channel,
        public string $customerName,
        public int $totalMinor,
        public OrderPaymentStatus $paymentStatus,
        public OrderStatus $status,
        public CarbonImmutable $placedAt,
    ) {}
}
