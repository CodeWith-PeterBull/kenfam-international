<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use App\Modules\Commerce\Reporting\Enums\CommerceDashboardRange;
use Carbon\CarbonImmutable;

/**
 * Immutable cross-domain read model consumed by the Commerce overview page.
 */
final readonly class CommerceDashboardSnapshot
{
    /**
     * @param  list<SalesSeriesPoint>  $salesSeries
     * @param  list<ChannelSummary>  $channelSummaries
     * @param  list<PaymentMethodSummary>  $paymentMethodSummaries
     * @param  list<OrderStatusSummary>  $actionQueue
     * @param  list<RecentOrderSummary>  $recentOrders
     * @param  list<StockAlertSummary>  $stockAlerts
     * @param  list<ActiveTillSummary>  $activeTills
     */
    public function __construct(
        public CommerceDashboardRange $range,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $currencyCode,
        public int $paymentsCollectedMinor,
        public int $orderValueMinor,
        public int $ordersReceived,
        public int $averageOrderValueMinor,
        public int $actionableOrders,
        public int $lowStockCount,
        public int $outOfStockCount,
        public int $activeTillCount,
        public int $activeCustomerCount,
        public array $salesSeries,
        public array $channelSummaries,
        public array $paymentMethodSummaries,
        public array $actionQueue,
        public array $recentOrders,
        public array $stockAlerts,
        public array $activeTills,
    ) {}

    /**
     * Return the concise inclusive period shown in the dashboard header.
     */
    public function periodLabel(): string
    {
        return $this->startsAt->format('d M Y').' - '.$this->endsAt->format('d M Y');
    }
}
