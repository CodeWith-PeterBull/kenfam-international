<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use App\Modules\Commerce\Orders\Enums\OrderChannel;

/**
 * Selected-period order count and non-cancelled value for one sales channel.
 */
final readonly class ChannelSummary
{
    public function __construct(
        public OrderChannel $channel,
        public int $orderCount,
        public int $orderValueMinor,
    ) {}
}
