<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Reports;

use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Reporting\Data\Rows\OrderRow;
use App\Modules\Commerce\Reporting\Filters\OrderReportFilters;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the order register PDF (web and POS) from admin-list filters.
 */
final class OrderRegisterReport extends CommerceListReport
{
    public function render(OrderReportFilters $filters, User $actor, ReportOrientation $orientation = ReportOrientation::Landscape): RenderedPdf
    {
        $rows = $this->rows($filters);
        $total = $this->total($filters);

        $context = ReportContext::forUser(
            user: $actor,
            title: 'Order register',
            subtitle: 'Web and point-of-sale orders',
            filename: 'order-register',
            orientation: $orientation,
            filters: $this->filterLabels($filters),
        );

        return $this->reports->render('commerce::reports.pdf.order-register', [
            'rows' => $rows,
            'total' => $total,
            'shown' => count($rows),
            'truncated' => $this->wasTruncated($total, count($rows)),
            'metrics' => $this->metrics($filters),
        ], $context);
    }

    /** @return list<OrderRow> */
    public function rows(OrderReportFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Order $order): OrderRow => new OrderRow(
                orderNumber: (string) $order->order_number,
                placedAt: $order->placed_at?->format('d M Y, H:i') ?? '—',
                customer: $order->customer_display_name !== '' ? $order->customer_display_name : 'Walk-in',
                channel: $order->channel->label(),
                status: $order->status->label(),
                paymentStatus: $order->payment_status->label(),
                itemCount: (int) $order->items_count,
                total: MoneyFormatter::format((int) $order->total_minor),
                paid: MoneyFormatter::format((int) $order->paid_minor),
                balance: MoneyFormatter::format((int) $order->balance_due_minor),
            ))
            ->all();
    }

    public function total(OrderReportFilters $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    /** @return list<string> */
    public function filterLabels(OrderReportFilters $filters): array
    {
        $labels = [];

        if ($filters->search !== '') {
            $labels[] = "Search: {$filters->search}";
        }

        if ($filters->channel instanceof OrderChannel) {
            $labels[] = "Channel: {$filters->channel->label()}";
        }

        if ($filters->status instanceof OrderStatus) {
            $labels[] = "Status: {$filters->status->label()}";
        }

        if ($filters->paymentStatus instanceof OrderPaymentStatus) {
            $labels[] = "Payment: {$filters->paymentStatus->label()}";
        }

        return $labels;
    }

    /** @return array<string, string> */
    private function metrics(OrderReportFilters $filters): array
    {
        $invoiced = (int) $this->baseQuery($filters)->sum('total_minor');
        $collected = (int) $this->baseQuery($filters)->sum('paid_minor');

        return [
            'Orders' => number_format($this->total($filters)),
            'Invoiced' => MoneyFormatter::format($invoiced),
            'Collected' => MoneyFormatter::format($collected),
            'Outstanding' => MoneyFormatter::format(max(0, $invoiced - $collected)),
        ];
    }

    private function baseQuery(OrderReportFilters $filters): Builder
    {
        return Order::query()
            ->whereNotNull('order_number')
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $match) use ($filters): void {
                    $match->where('order_number', 'like', "%{$filters->search}%")
                        ->orWhere('customer_first_name', 'like', "%{$filters->search}%")
                        ->orWhere('customer_last_name', 'like', "%{$filters->search}%")
                        ->orWhere('customer_email', 'like', "%{$filters->search}%")
                        ->orWhere('customer_phone', 'like', "%{$filters->search}%");
                });
            })
            ->when($filters->channel instanceof OrderChannel, fn (Builder $query) => $query->where('channel', $filters->channel->value))
            ->when($filters->status instanceof OrderStatus, fn (Builder $query) => $query->where('status', $filters->status->value))
            ->when($filters->paymentStatus instanceof OrderPaymentStatus, fn (Builder $query) => $query->where('payment_status', $filters->paymentStatus->value));
    }
}
