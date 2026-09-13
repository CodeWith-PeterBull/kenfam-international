<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Filters;

use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;

/**
 * Normalized order register report filters, mirroring the admin list screen.
 */
final readonly class OrderReportFilters
{
    public function __construct(
        public string $search = '',
        public ?OrderChannel $channel = null,
        public ?OrderStatus $status = null,
        public ?OrderPaymentStatus $paymentStatus = null,
    ) {}

    public static function fromInputs(string $search = '', string $channel = '', string $status = '', string $paymentStatus = ''): self
    {
        return new self(
            search: trim($search),
            channel: OrderChannel::tryFrom($channel),
            status: OrderStatus::tryFrom($status),
            paymentStatus: OrderPaymentStatus::tryFrom($paymentStatus),
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->channel !== null || $this->status !== null || $this->paymentStatus !== null;
    }
}
