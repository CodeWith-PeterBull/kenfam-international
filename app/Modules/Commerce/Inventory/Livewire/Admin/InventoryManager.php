<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Livewire\Admin;

use App\Contracts\RecordsSystemActivity;
use App\Data\RenderedPdf;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Exceptions\InsufficientStockException;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Livewire\Forms\StockAdjustmentForm;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Inventory\Services\InventoryService;
use App\Modules\Commerce\Reporting\Filters\StockLevelReportFilters;
use App\Modules\Commerce\Reporting\Filters\StockMovementReportFilters;
use App\Modules\Commerce\Reporting\Reports\StockLevelReport;
use App\Modules\Commerce\Reporting\Reports\StockMovementReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Current inventory projections, manual adjustments, and immutable ledger history.
 */
final class InventoryManager extends Component
{
    use WithPagination;

    #[Url(as: 'inventory-q', except: '')]
    public string $search = '';

    #[Url(as: 'stock-state', except: '')]
    public string $stockFilter = '';

    #[Url(as: 'movement-type', except: '')]
    public string $movementTypeFilter = '';

    #[Url(as: 'inventory-per-page', except: 15)]
    public int $perPage = 15;

    public StockAdjustmentForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedStockId = null;

    public function boot(): void
    {
        Gate::authorize('viewAny', Stock::class);
        Gate::authorize('viewAny', StockMovement::class);
    }

    /**
     * Export the current filtered stock levels as a PDF register (Livewire download).
     */
    public function exportStockLevelsPdf(StockLevelReport $report, RecordsSystemActivity $activities): StreamedResponse
    {
        Gate::authorize('viewAny', Stock::class);
        $actor = $this->actor();
        $filters = StockLevelReportFilters::fromInputs($this->search, $this->stockFilter);
        $rendered = $report->render($filters, $actor);

        $activities->record(
            activityType: 'commerce.inventory.stock_report_exported',
            description: 'Stock levels report exported',
            actor: $actor,
            properties: ['filters' => $report->filterLabels($filters), 'pages' => $rendered->pageCount],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-reports',
        );

        return $this->streamPdf($rendered);
    }

    /**
     * Export the current filtered stock ledger as a PDF register (Livewire download).
     */
    public function exportMovementsPdf(StockMovementReport $report, RecordsSystemActivity $activities): StreamedResponse
    {
        Gate::authorize('viewAny', StockMovement::class);
        $actor = $this->actor();
        $filters = StockMovementReportFilters::fromInputs($this->search, $this->movementTypeFilter);
        $rendered = $report->render($filters, $actor);

        $activities->record(
            activityType: 'commerce.inventory.movement_report_exported',
            description: 'Stock movement history report exported',
            actor: $actor,
            properties: ['filters' => $report->filterLabels($filters), 'pages' => $rendered->pageCount],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-reports',
        );

        return $this->streamPdf($rendered);
    }

    private function streamPdf(RenderedPdf $rendered): StreamedResponse
    {
        return response()->streamDownload(static function () use ($rendered): void {
            echo $rendered->contents;
        }, $rendered->filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetInventoryPages();
    }

    public function updatedStockFilter(): void
    {
        $this->resetPage('stockPage');
        $this->refreshInventory();
    }

    public function updatedMovementTypeFilter(): void
    {
        $this->resetPage('movementPage');
        $this->refreshInventory();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetPage('stockPage');
        $this->refreshInventory();
    }

    /** @return list<StockMovementType> */
    #[Computed]
    public function movementTypes(): array
    {
        return StockMovementType::cases();
    }

    /** @return array{products: int, units: int, low: int, out: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'products' => Stock::query()->count(),
            'units' => (int) Stock::query()->sum('on_hand'),
            'low' => Stock::query()
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '>', 0)
                ->whereColumn('on_hand', '<=', 'low_stock_threshold')
                ->count(),
            'out' => Stock::query()
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '<=', 0)
                ->count(),
        ];
    }

    #[Computed]
    public function stocks(): LengthAwarePaginator
    {
        return $this->filteredStocks()
            ->with(['product.category'])
            ->orderBy('on_hand')
            ->orderBy('id')
            ->paginate($this->validPerPage(), ['*'], 'stockPage');
    }

    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return StockMovement::query()
            ->with(['product', 'creator', 'reference'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('product', function (Builder $product) use ($search): void {
                    $product->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when(StockMovementType::tryFrom($this->movementTypeFilter), fn (Builder $query, StockMovementType $type) => $query->where('type', $type->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'movementPage');
    }

    #[Computed]
    public function selectedStock(): ?Stock
    {
        if ($this->selectedStockId === null) {
            return null;
        }

        return Stock::query()->with('product')->find($this->selectedStockId);
    }

    public function openAdjustment(int $stockId): void
    {
        $stock = $this->findStock($stockId);
        Gate::authorize('adjust', $stock);
        $this->closeDialog();
        $this->selectedStockId = $stock->id;
        $this->form->resetForAdjustment();
        $this->dialog = 'adjust';
        unset($this->selectedStock);
    }

    public function adjust(InventoryService $inventory): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $stock = $this->findStock($this->selectedStockId);
        Gate::authorize('adjust', $stock);

        try {
            $inventory->adjust(
                product: $stock->product,
                quantityDelta: $this->form->signedQuantity(),
                note: $this->form->note,
                actor: $this->actor(),
            );
        } catch (InsufficientStockException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Stock adjustment posted to the immutable ledger.');
        $this->refreshInventory();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedStockId = null;
        $this->form->resetForAdjustment();
        $this->resetValidation();
        unset($this->selectedStock);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->stockFilter = '';
        $this->movementTypeFilter = '';
        $this->resetInventoryPages();
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.inventory-manager');
    }

    private function filteredStocks(): Builder
    {
        $search = trim($this->search);

        return Stock::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('product', function (Builder $product) use ($search): void {
                    $product->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($this->stockFilter === 'healthy', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->whereColumn('on_hand', '>', 'low_stock_threshold'))
            ->when($this->stockFilter === 'low', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '>', 0)
                ->whereColumn('on_hand', '<=', 'low_stock_threshold'))
            ->when($this->stockFilter === 'out', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $product) => $product->where('track_stock', true))
                ->where('on_hand', '<=', 0))
            ->when($this->stockFilter === 'untracked', fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->where('track_stock', false)));
    }

    private function findStock(?int $stockId): Stock
    {
        abort_if($stockId === null, 404);

        return Stock::query()->with('product')->findOrFail($stockId);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function validPerPage(): int
    {
        return in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
    }

    private function resetInventoryPages(): void
    {
        $this->resetPage('stockPage');
        $this->resetPage('movementPage');
        $this->refreshInventory();
    }

    private function refreshInventory(): void
    {
        unset($this->stocks, $this->movements, $this->statistics, $this->selectedStock);
    }
}
