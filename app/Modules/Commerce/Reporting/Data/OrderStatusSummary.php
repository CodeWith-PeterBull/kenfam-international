<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use App\Modules\Commerce\Orders\Enums\OrderStatus;

/**
 * Current actionable web-order count for one fulfillment state.
 */
final readonly class OrderStatusSummary
{
    public function __construct(
        public OrderStatus $status,
        public int $orderCount,
    ) {}
}
