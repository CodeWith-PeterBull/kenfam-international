<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\RenderedPdf;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Modules\Commerce\Catalog\Support\BarcodeImageGenerator;
use App\Modules\Commerce\Exceptions\CatalogException;
use Carbon\CarbonImmutable;

/**
 * Renders an A4 barcode label sheet through the shared institutional PDF engine.
 *
 * Each selection expands into its requested number of identical label cells;
 * cells are laid out as a fixed-column table (DOMPDF has no CSS grid) and each
 * barcode is embedded as a server-generated Code 128 PNG so the printed label
 * is scannable. No new PDF engine is introduced.
 */
final readonly class BarcodeLabelDocumentService
{
    public function __construct(
        private RendersPdfReports $reports,
        private ResolvesInstitutionProfile $profiles,
        private BarcodeImageGenerator $barcodes,
    ) {}

    /**
     * Build the label-sheet PDF for the supplied selections.
     *
     * @param  list<array{value: string, name: string, priceLabel: string, quantity: int}>  $selections
     * @param  array<string, mixed>  $options
     */
    public function pdf(array $selections, array $options = []): RenderedPdf
    {
        return $this->reports->render(
            view: 'commerce::reports.barcode-labels',
            data: $this->viewData($selections, $options),
            context: $this->context(),
        );
    }

    /**
     * @param  list<array{value: string, name: string, priceLabel: string, quantity: int}>  $selections
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function viewData(array $selections, array $options): array
    {
        $columns = max(1, min(6, (int) ($options['columns'] ?? config('commerce.barcode.label.columns', 3))));
        $maximum = max(1, (int) config('commerce.barcode.label.max_labels_per_run', 300));

        $cells = [];
        $imageCache = [];
        foreach ($selections as $selection) {
            $value = strtoupper(trim((string) ($selection['value'] ?? '')));
            $quantity = max(0, (int) ($selection['quantity'] ?? 0));
            if ($value === '' || $quantity === 0) {
                continue;
            }

            $imageCache[$value] ??= $this->barcodes->pngDataUri($value);
            for ($index = 0; $index < $quantity && count($cells) < $maximum; $index++) {
                $cells[] = [
                    'value' => $value,
                    'name' => (string) ($selection['name'] ?? ''),
                    'priceLabel' => (string) ($selection['priceLabel'] ?? ''),
                    'barcode' => $imageCache[$value],
                ];
            }
        }

        if ($cells === []) {
            throw new CatalogException('Select at least one product with a printable barcode.');
        }

        $rows = array_chunk($cells, $columns);
        // Pad the final row so the table keeps even column widths.
        $lastIndex = count($rows) - 1;
        while (count($rows[$lastIndex]) < $columns) {
            $rows[$lastIndex][] = null;
        }

        $profile = $this->profiles->current();

        return [
            'rows' => $rows,
            'cellWidth' => (int) floor(100 / $columns),
            'storeName' => $profile->shortName,
            'labelCount' => count($cells),
            'showStoreName' => (bool) ($options['show_store_name'] ?? config('commerce.barcode.label.show_store_name', true)),
            'showProductName' => (bool) ($options['show_product_name'] ?? config('commerce.barcode.label.show_product_name', true)),
            'showPrice' => (bool) ($options['show_price'] ?? config('commerce.barcode.label.show_price', true)),
        ];
    }

    private function context(): ReportContext
    {
        $profile = $this->profiles->current();

        return new ReportContext(
            title: 'Barcode labels',
            subtitle: 'Product barcode label sheet',
            filename: ReportContext::sanitizeFilename('barcode-labels-'.now()->format('Ymd-His').'.pdf'),
            orientation: ReportOrientation::Portrait,
            generatedAt: CarbonImmutable::now(),
            generatedBy: "{$profile->shortName} Commerce",
            filters: [],
        );
    }
}
