<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Services;

use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Reporting\Data\ActiveTillSummary;
use App\Modules\Commerce\Reporting\Data\ChannelSummary;
use App\Modules\Commerce\Reporting\Data\CommerceDashboardSnapshot;
use App\Modules\Commerce\Reporting\Data\OrderStatusSummary;
use App\Modules\Commerce\Reporting\Data\PaymentMethodSummary;
use App\Modules\Commerce\Reporting\Data\RecentOrderSummary;
use App\Modules\Commerce\Reporting\Data\SalesSeriesPoint;
use App\Modules\Commerce\Reporting\Data\StockAlertSummary;
use App\Modules\Commerce\Reporting\Enums\CommerceDashboardRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds bounded, read-only Commerce aggregates without mutating domain state.
 */
final readonly class CommerceDashboardService
{
    /**
     * Build the complete Commerce overview for one inclusive calendar range.
     */
    public function snapshot(CommerceDashboardRange $range, ?CarbonImmutable $now = null): CommerceDashboardSnapshot
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $startsAt = $range->startsAt($now);
        $endsAt = $now->endOfDay();

        $periodOrders = $this->periodOrders($startsAt, $endsAt);
        $contributingOrders = (clone $periodOrders)
            ->where('status', '!=', OrderStatus::Cancelled->value);
        $contributingOrderCount = (clone $contributingOrders)->count();
        $orderValueMinor = (int) (clone $contributingOrders)->sum('total_minor');
        $payments = $this->completedPayments($startsAt, $endsAt);
        $paymentsCollectedMinor = (int) (clone $payments)->sum('amount_minor');
        $lowStockCount = $this->lowStockQuery()->where('on_hand', '>', 0)->count();
        $outOfStockCount = $this->lowStockQuery()->where('on_hand', '<=', 0)->count();
        $actionQueue = $this->actionQueue();

        return new CommerceDashboardSnapshot(
            range: $range,
            startsAt: $startsAt,
            endsAt: $endsAt,
            currencyCode: (string) config('commerce.currency.code', 'KES'),
            paymentsCollectedMinor: $paymentsCollectedMinor,
            orderValueMinor: $orderValueMinor,
            ordersReceived: (clone $periodOrders)->count(),
            averageOrderValueMinor: $contributingOrderCount > 0
                ? intdiv($orderValueMinor, $contributingOrderCount)
                : 0,
            actionableOrders: array_sum(array_map(
                static fn (OrderStatusSummary $summary): int => $summary->orderCount,
                $actionQueue,
            )),
            lowStockCount: $lowStockCount,
            outOfStockCount: $outOfStockCount,
            activeTillCount: TillSession::query()
                ->where('status', TillSessionStatus::Open->value)
                ->whereNull('closed_at')
                ->count(),
            activeCustomerCount: Customer::query()->count(),
            salesSeries: $this->salesSeries($range, $startsAt, $endsAt),
            channelSummaries: $this->channelSummaries($periodOrders),
            paymentMethodSummaries: $this->paymentMethodSummaries($payments),
            actionQueue: $actionQueue,
            recentOrders: $this->recentOrders($periodOrders),
            stockAlerts: $this->stockAlerts(),
            activeTills: $this->activeTills(),
        );
    }

    /**
     * Return numbered orders placed inside the selected inclusive period.
     */
    private function periodOrders(CarbonImmutable $startsAt, CarbonImmutable $endsAt): Builder
    {
        return Order::query()
            ->whereNotNull('order_number')
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$startsAt, $endsAt])
            ->where('status', '!=', OrderStatus::Held->value);
    }

    /**
     * Return completed payments collected inside the selected period.
     */
    private function completedPayments(CarbonImmutable $startsAt, CarbonImmutable $endsAt): Builder
    {
        return Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startsAt, $endsAt]);
    }

    /**
     * Return tracked stock projections at or below their warning threshold.
     */
    private function lowStockQuery(): Builder
    {
        return Stock::query()
            ->whereHas('product', static fn (Builder $product): Builder => $product->where('track_stock', true))
            ->whereColumn('on_hand', '<=', 'low_stock_threshold');
    }

    /**
     * Build a zero-filled cross-channel daily collection series.
     *
     * @return list<SalesSeriesPoint>
     */
    private function salesSeries(
        CommerceDashboardRange $range,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): array {
        $rows = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', PaymentStatus::Completed->value)
            ->whereNotNull('payments.paid_at')
            ->whereBetween('payments.paid_at', [$startsAt, $endsAt])
            ->whereIn('orders.channel', array_map(
                static fn (OrderChannel $channel): string => $channel->value,
                OrderChannel::cases(),
            ))
            ->selectRaw('DATE(payments.paid_at) as paid_date')
            ->selectRaw('orders.channel as order_channel')
            ->selectRaw('SUM(payments.amount_minor) as total_minor')
            ->groupByRaw('DATE(payments.paid_at), orders.channel')
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row->paid_date][(string) $row->order_channel] = (int) $row->total_minor;
        }

        $series = [];
        for ($day = $startsAt->startOfDay(); $day->lte($endsAt); $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = new SalesSeriesPoint(
                date: $date,
                label: $range === CommerceDashboardRange::NinetyDays
                    ? $day->format('d M')
                    : $day->format('D d'),
                webMinor: $indexed[$date][OrderChannel::Web->value] ?? 0,
                pointOfSaleMinor: $indexed[$date][OrderChannel::PointOfSale->value] ?? 0,
            );
        }

        return $series;
    }

    /**
     * Build period order counts and non-cancelled value per channel.
     *
     * @return list<ChannelSummary>
     */
    private function channelSummaries(Builder $periodOrders): array
    {
        $rows = (clone $periodOrders)
            ->selectRaw('channel, COUNT(*) as order_count')
            ->selectRaw(
                'SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as order_value_minor',
                [OrderStatus::Cancelled->value],
            )
            ->groupBy('channel')
            ->get()
            ->keyBy(static function (Order $row): string {
                return $row->channel instanceof OrderChannel ? $row->channel->value : (string) $row->channel;
            });

        return array_map(static function (OrderChannel $channel) use ($rows): ChannelSummary {
            $row = $rows->get($channel->value);

            return new ChannelSummary(
                channel: $channel,
                orderCount: (int) ($row?->order_count ?? 0),
                orderValueMinor: (int) ($row?->order_value_minor ?? 0),
            );
        }, OrderChannel::cases());
    }

    /**
     * Build completed-payment counts and values per tender method.
     *
     * @return list<PaymentMethodSummary>
     */
    private function paymentMethodSummaries(Builder $payments): array
    {
        return (clone $payments)
            ->selectRaw('method, COUNT(*) as payment_count, SUM(amount_minor) as total_minor')
            ->groupBy('method')
            ->orderByDesc('total_minor')
            ->get()
            ->map(static function (Payment $row): ?PaymentMethodSummary {
                $method = $row->method instanceof PaymentMethod
                    ? $row->method
                    : PaymentMethod::tryFrom((string) $row->method);

                return $method === null ? null : new PaymentMethodSummary(
                    method: $method,
                    paymentCount: (int) $row->payment_count,
                    totalMinor: (int) $row->total_minor,
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build the current web-order action queue in lifecycle order.
     *
     * @return list<OrderStatusSummary>
     */
    private function actionQueue(): array
    {
        $statuses = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Ready,
        ];
        $rows = Order::query()
            ->where('channel', OrderChannel::Web->value)
            ->whereIn('status', array_map(static fn (OrderStatus $status): string => $status->value, $statuses))
            ->selectRaw('status, COUNT(*) as order_count')
            ->groupBy('status')
            ->get()
            ->keyBy(static function (Order $row): string {
                return $row->status instanceof OrderStatus ? $row->status->value : (string) $row->status;
            });

        return array_map(static function (OrderStatus $status) use ($rows): OrderStatusSummary {
            return new OrderStatusSummary(
                status: $status,
                orderCount: (int) ($rows->get($status->value)?->order_count ?? 0),
            );
        }, $statuses);
    }

    /**
     * Build a bounded, safe recent-order projection for the selected period.
     *
     * @return list<RecentOrderSummary>
     */
    private function recentOrders(Builder $periodOrders): array
    {
        return (clone $periodOrders)
            ->latest('placed_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(static fn (Order $order): RecentOrderSummary => new RecentOrderSummary(
                ulid: (string) $order->ulid,
                orderNumber: (string) $order->order_number,
                channel: $order->channel,
                customerName: $order->customer_display_name,
                totalMinor: (int) $order->total_minor,
                paymentStatus: $order->payment_status,
                status: $order->status,
                placedAt: $order->placed_at->toImmutable(),
            ))
            ->all();
    }

    /**
     * Build the most urgent bounded stock exception list.
     *
     * @return list<StockAlertSummary>
     */
    private function stockAlerts(): array
    {
        return $this->lowStockQuery()
            ->with('product')
            ->orderBy('on_hand')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(static fn (Stock $stock): ?StockAlertSummary => $stock->product === null
                ? null
                : new StockAlertSummary(
                    productUlid: (string) $stock->product->ulid,
                    productName: $stock->product->name,
                    sku: $stock->product->sku,
                    onHand: (int) $stock->on_hand,
                    lowStockThreshold: (int) $stock->low_stock_threshold,
                    state: $stock->on_hand <= 0 ? 'out' : 'low',
                ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build the bounded current till list with register and cashier context.
     *
     * @return list<ActiveTillSummary>
     */
    private function activeTills(): array
    {
        return TillSession::query()
            ->where('status', TillSessionStatus::Open->value)
            ->whereNull('closed_at')
            ->with(['register', 'opener.profile'])
            ->latest('opened_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(static fn (TillSession $session): ActiveTillSummary => new ActiveTillSummary(
                ulid: (string) $session->ulid,
                registerName: $session->register->name,
                registerCode: $session->register->code,
                cashierName: $session->opener->display_name,
                openedAt: $session->opened_at->toImmutable(),
                expectedCashMinor: (int) $session->expected_cash_minor,
            ))
            ->all();
    }
}
