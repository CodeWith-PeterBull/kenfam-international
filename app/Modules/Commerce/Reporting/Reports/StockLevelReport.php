<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Reports;

use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Reporting\Data\Rows\StockLevelRow;
use App\Modules\Commerce\Reporting\Filters\StockLevelReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the current stock-level register PDF from inventory-list filters.
 */
final class StockLevelReport extends CommerceListReport
{
    private const STATE_LABELS = [
        'healthy' => 'Healthy',
        'low' => 'Low stock',
        'out' => 'Out of stock',
        'untracked' => 'Untracked',
    ];

    public function render(StockLevelReportFilters $filters, User $actor, ReportOrientation $orientation = ReportOrientation::Landscape): RenderedPdf
    {
        $rows = $this->rows($filters);
        $total = $this->total($filters);

        $context = ReportContext::forUser(
            user: $actor,
            title: 'Stock levels',
            subtitle: 'Current inventory projection',
            filename: 'stock-levels',
            orientation: $orientation,
            filters: $this->filterLabels($filters),
        );

        return $this->reports->render('commerce::reports.pdf.stock-levels', [
            'rows' => $rows,
            'total' => $total,
            'shown' => count($rows),
            'truncated' => $this->wasTruncated($total, count($rows)),
            'metrics' => $this->metrics($filters),
        ], $context);
    }

    /** @return list<StockLevelRow> */
    public function rows(StockLevelReportFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->with(['product.category'])
            ->orderBy('on_hand')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Stock $stock): StockLevelRow => new StockLevelRow(
                sku: (string) ($stock->product?->sku ?? '—'),
                name: (string) ($stock->product?->name ?? '—'),
                category: $stock->product?->category?->name ?? '—',
                onHand: (int) $stock->on_hand,
                threshold: (int) $stock->low_stock_threshold,
                available: $stock->available(),
                state: $this->stateFor($stock),
            ))
            ->all();
    }

    public function total(StockLevelReportFilters $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    /** @return list<string> */
    public function filterLabels(StockLevelReportFilters $filters): array
    {
        $labels = [];

        if ($filters->search !== '') {
            $labels[] = "Search: {$filters->search}";
        }

        if ($filters->stockState !== null) {
            $labels[] = 'Stock state: '.(self::STATE_LABELS[$filters->stockState] ?? $filters->stockState);
        }

        return $labels;
    }

    private function stateFor(Stock $stock): string
    {
        if (! ($stock->product?->track_stock ?? false)) {
            return self::STATE_LABELS['untracked'];
        }

        if ($stock->on_hand <= 0) {
            return self::STATE_LABELS['out'];
        }

        return $stock->isLow() ? self::STATE_LABELS['low'] : self::STATE_LABELS['healthy'];
    }

    /** @return array<string, string> */
    private function metrics(StockLevelReportFilters $filters): array
    {
        $low = $this->baseQuery($filters)
            ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
            ->where('on_hand', '>', 0)
            ->whereColumn('on_hand', '<=', 'low_stock_threshold')
            ->count();

        $out = $this->baseQuery($filters)
            ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
            ->where('on_hand', '<=', 0)
            ->count();

        return [
            'Products' => number_format($this->total($filters)),
            'Units on hand' => number_format((int) $this->baseQuery($filters)->sum('on_hand')),
            'Low stock' => number_format($low),
            'Out of stock' => number_format($out),
        ];
    }

    private function baseQuery(StockLevelReportFilters $filters): Builder
    {
        return Stock::query()
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('product', function (Builder $product) use ($filters): void {
                    $product->where('name', 'like', "%{$filters->search}%")
                        ->orWhere('sku', 'like', "%{$filters->search}%")
                        ->orWhere('barcode', 'like', "%{$filters->search}%");
                });
            })
            ->when($filters->stockState === 'healthy', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->whereColumn('on_hand', '>', 'low_stock_threshold'))
            ->when($filters->stockState === 'low', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '>', 0)
                ->whereColumn('on_hand', '<=', 'low_stock_threshold'))
            ->when($filters->stockState === 'out', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '<=', 0))
            ->when($filters->stockState === 'untracked', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', false)));
    }
}
