<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Reports;

use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Reporting\Data\Rows\StockMovementRow;
use App\Modules\Commerce\Reporting\Filters\StockMovementReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the immutable stock-movement history register PDF from ledger filters.
 */
final class StockMovementReport extends CommerceListReport
{
    public function render(StockMovementReportFilters $filters, User $actor, ReportOrientation $orientation = ReportOrientation::Landscape): RenderedPdf
    {
        $rows = $this->rows($filters);
        $total = $this->total($filters);

        $context = ReportContext::forUser(
            user: $actor,
            title: 'Stock movement history',
            subtitle: 'Immutable inventory ledger',
            filename: 'stock-movements',
            orientation: $orientation,
            filters: $this->filterLabels($filters),
        );

        return $this->reports->render('commerce::reports.pdf.stock-movements', [
            'rows' => $rows,
            'total' => $total,
            'shown' => count($rows),
            'truncated' => $this->wasTruncated($total, count($rows)),
            'metrics' => $this->metrics($filters),
        ], $context);
    }

    /** @return list<StockMovementRow> */
    public function rows(StockMovementReportFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->with(['product', 'creator', 'reference'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (StockMovement $movement): StockMovementRow => new StockMovementRow(
                occurredAt: $movement->created_at?->format('d M Y, H:i') ?? '—',
                product: (string) ($movement->product?->name ?? '—'),
                sku: (string) ($movement->product?->sku ?? '—'),
                type: $movement->type->label(),
                quantityDelta: ($movement->quantity_delta > 0 ? '+' : '').number_format((int) $movement->quantity_delta),
                balanceAfter: (int) $movement->balance_after,
                reference: $this->referenceLabel($movement),
                actor: $movement->creator?->display_name ?? 'System',
            ))
            ->all();
    }

    public function total(StockMovementReportFilters $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    /** @return list<string> */
    public function filterLabels(StockMovementReportFilters $filters): array
    {
        $labels = [];

        if ($filters->search !== '') {
            $labels[] = "Search: {$filters->search}";
        }

        if ($filters->movementType instanceof StockMovementType) {
            $labels[] = "Movement type: {$filters->movementType->label()}";
        }

        return $labels;
    }

    private function referenceLabel(StockMovement $movement): string
    {
        $reference = $movement->reference;

        return $reference instanceof Order ? (string) $reference->order_number : '—';
    }

    /** @return array<string, string> */
    private function metrics(StockMovementReportFilters $filters): array
    {
        $in = (int) $this->baseQuery($filters)->where('quantity_delta', '>', 0)->sum('quantity_delta');
        $out = (int) $this->baseQuery($filters)->where('quantity_delta', '<', 0)->sum('quantity_delta');

        return [
            'Movements' => number_format($this->total($filters)),
            'Units in' => '+'.number_format($in),
            'Units out' => number_format($out),
            'Net change' => ($in + $out >= 0 ? '+' : '').number_format($in + $out),
        ];
    }

    private function baseQuery(StockMovementReportFilters $filters): Builder
    {
        return StockMovement::query()
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('product', function (Builder $product) use ($filters): void {
                    $product->where('name', 'like', "%{$filters->search}%")
                        ->orWhere('sku', 'like', "%{$filters->search}%");
                });
            })
            ->when($filters->movementType instanceof StockMovementType, fn (Builder $query) => $query->where('type', $filters->movementType->value));
    }
}
