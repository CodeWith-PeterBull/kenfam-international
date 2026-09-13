<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Livewire\Admin;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Services\BarcodeLabelDocumentService;
use App\Modules\Commerce\Catalog\Support\BarcodeImageGenerator;
use App\Modules\Commerce\Exceptions\CatalogException;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Independent barcode label workspace embeddable under catalog administration.
 *
 * The cashier or stock officer builds a print run (products, per-product label
 * quantities, which barcode to encode, and what text to show), previews each
 * label with a server-generated Code 128 image, then downloads an A4 label
 * sheet PDF. Preview and print consume the same generator, so they match.
 */
final class BarcodeLabelSheet extends Component
{
    public string $search = '';

    #[Url(as: 'product')]
    public string $preselect = '';

    /** @var list<array{id: int, quantity: int, source: string}> */
    public array $items = [];

    public int $columns = 3;

    public bool $showStoreName = true;

    public bool $showProductName = true;

    public bool $showPrice = true;

    public function boot(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    public function mount(): void
    {
        $this->columns = max(1, min(6, (int) config('commerce.barcode.label.columns', 3)));
        $this->showStoreName = (bool) config('commerce.barcode.label.show_store_name', true);
        $this->showProductName = (bool) config('commerce.barcode.label.show_product_name', true);
        $this->showPrice = (bool) config('commerce.barcode.label.show_price', true);

        if ($this->preselect !== '') {
            $product = Product::query()->where('ulid', $this->preselect)->first();
            if ($product instanceof Product) {
                $this->addProduct($product->id);
            }
        }
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function results(): Collection
    {
        $search = trim($this->search);
        $selectedIds = array_column($this->items, 'id');

        return Product::query()
            ->whereNotIn('id', $selectedIds)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $match = '%'.$search.'%';
                $query->where(fn (Builder $lookup): Builder => $lookup
                    ->where('name', 'like', $match)
                    ->orWhere('sku', 'like', $match)
                    ->orWhere('barcode', 'like', $match)
                    ->orWhere('manufacturer_barcode', 'like', $match));
            })
            ->orderBy('name')
            ->limit(12)
            ->get();
    }

    /**
     * Resolve the working list into display rows with a live barcode preview.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        if ($this->items === []) {
            return [];
        }

        $barcodes = app(BarcodeImageGenerator::class);
        $products = Product::query()
            ->whereIn('id', array_column($this->items, 'id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($this->items as $index => $item) {
            $product = $products->get($item['id']);
            if (! $product instanceof Product) {
                continue;
            }

            $value = $this->valueFor($product, $item['source']);
            $rows[] = [
                'index' => $index,
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'internalBarcode' => (string) $product->barcode,
                'manufacturerBarcode' => (string) $product->manufacturer_barcode,
                'hasManufacturer' => filled($product->manufacturer_barcode),
                'source' => $item['source'],
                'quantity' => $item['quantity'],
                'value' => $value,
                'priceLabel' => MoneyFormatter::format((int) $product->effective_price_minor),
                'preview' => $value === '' ? null : $barcodes->pngDataUri($value, 2, 44),
            ];
        }

        return $rows;
    }

    public function addProduct(int $productId): void
    {
        if (in_array($productId, array_column($this->items, 'id'), true)) {
            return;
        }

        $product = Product::query()->find($productId);
        if (! $product instanceof Product) {
            return;
        }

        $this->items[] = [
            'id' => $product->id,
            'quantity' => 1,
            'source' => filled($product->barcode) ? 'internal' : 'manufacturer',
        ];
        $this->search = '';
        unset($this->results, $this->rows);
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        unset($this->results, $this->rows);
    }

    public function clearItems(): void
    {
        $this->items = [];
        unset($this->results, $this->rows);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['quantity'] = max(1, min(500, (int) ($item['quantity'] ?? 1)));
            $this->items[$index]['source'] = in_array($item['source'] ?? 'internal', ['internal', 'manufacturer'], true)
                ? $item['source']
                : 'internal';
        }
        unset($this->rows);
    }

    /**
     * Stream the generated label sheet as a downloadable PDF.
     */
    public function generate(BarcodeLabelDocumentService $labels): ?StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);
        $this->resetErrorBag('sheet');

        $selections = [];
        foreach ($this->rows as $row) {
            if ($row['value'] === '') {
                continue;
            }
            $selections[] = [
                'value' => $row['value'],
                'name' => $row['name'],
                'priceLabel' => $row['priceLabel'],
                'quantity' => (int) $row['quantity'],
            ];
        }

        if ($selections === []) {
            $this->addError('sheet', 'Add at least one product that has a barcode to print.');

            return null;
        }

        try {
            $pdf = $labels->pdf($selections, [
                'columns' => $this->columns,
                'show_store_name' => $this->showStoreName,
                'show_product_name' => $this->showProductName,
                'show_price' => $this->showPrice,
            ]);
        } catch (CatalogException $exception) {
            $this->addError('sheet', $exception->getMessage());

            return null;
        }

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->contents;
            },
            $pdf->filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.barcode-label-sheet');
    }

    /**
     * Choose the barcode value to encode for one working-list row.
     */
    private function valueFor(Product $product, string $source): string
    {
        if ($source === 'manufacturer' && filled($product->manufacturer_barcode)) {
            return (string) $product->manufacturer_barcode;
        }

        return (string) ($product->barcode ?? $product->manufacturer_barcode ?? '');
    }
}
