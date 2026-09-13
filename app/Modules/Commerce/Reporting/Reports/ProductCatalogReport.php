<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Reports;

use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Reporting\Data\Rows\ProductRow;
use App\Modules\Commerce\Reporting\Filters\ProductReportFilters;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the product catalogue register PDF from admin-list filters.
 */
final class ProductCatalogReport extends CommerceListReport
{
    public function render(ProductReportFilters $filters, User $actor, ReportOrientation $orientation = ReportOrientation::Landscape): RenderedPdf
    {
        $rows = $this->rows($filters);
        $total = $this->total($filters);

        $context = ReportContext::forUser(
            user: $actor,
            title: 'Product catalogue',
            subtitle: 'Administrative product register',
            filename: 'product-catalogue',
            orientation: $orientation,
            filters: $this->filterLabels($filters),
        );

        return $this->reports->render('commerce::reports.pdf.product-catalogue', [
            'rows' => $rows,
            'total' => $total,
            'shown' => count($rows),
            'truncated' => $this->wasTruncated($total, count($rows)),
            'metrics' => $this->metrics($filters),
        ], $context);
    }

    /** @return list<ProductRow> */
    public function rows(ProductReportFilters $filters): array
    {
        return $this->baseQuery($filters)
            ->with(['category', 'stock'])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Product $product): ProductRow => new ProductRow(
                sku: (string) $product->sku,
                name: (string) $product->name,
                category: $product->category?->name ?? '—',
                status: $product->status->label(),
                listPrice: MoneyFormatter::format((int) $product->price_minor),
                salePrice: $product->isOnSale() ? MoneyFormatter::format((int) $product->sale_price_minor) : null,
                sellingPrice: MoneyFormatter::format((int) $product->effective_price_minor),
                featured: (bool) $product->is_featured,
                stock: $product->track_stock ? number_format((int) ($product->stock?->on_hand ?? 0)) : 'Untracked',
            ))
            ->all();
    }

    public function total(ProductReportFilters $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    /** @return list<string> */
    public function filterLabels(ProductReportFilters $filters): array
    {
        $labels = [];

        if ($filters->search !== '') {
            $labels[] = "Search: {$filters->search}";
        }

        if ($filters->status instanceof ProductStatus) {
            $labels[] = "Status: {$filters->status->label()}";
        }

        if ($filters->categoryId !== null) {
            $name = ProductCategory::query()->whereKey($filters->categoryId)->value('name');
            $labels[] = 'Category: '.($name ?? "#{$filters->categoryId}");
        }

        return $labels;
    }

    /** @return array<string, string> */
    private function metrics(ProductReportFilters $filters): array
    {
        return [
            'Products' => number_format($this->total($filters)),
            'Published' => number_format($this->baseQuery($filters)->where('status', ProductStatus::Published->value)->count()),
            'Drafts' => number_format($this->baseQuery($filters)->where('status', ProductStatus::Draft->value)->count()),
            'Featured' => number_format($this->baseQuery($filters)->where('is_featured', true)->count()),
        ];
    }

    private function baseQuery(ProductReportFilters $filters): Builder
    {
        return Product::query()
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $match) use ($filters): void {
                    $match->where('name', 'like', "%{$filters->search}%")
                        ->orWhere('sku', 'like', "%{$filters->search}%")
                        ->orWhere('barcode', 'like', "%{$filters->search}%")
                        ->orWhere('manufacturer_barcode', 'like', "%{$filters->search}%");
                });
            })
            ->when($filters->status instanceof ProductStatus, fn (Builder $query) => $query->where('status', $filters->status->value))
            ->when($filters->categoryId !== null, fn (Builder $query) => $query->where('category_id', $filters->categoryId));
    }
}
