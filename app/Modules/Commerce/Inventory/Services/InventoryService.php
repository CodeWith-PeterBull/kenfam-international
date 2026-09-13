<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Exceptions\InsufficientStockException;
use App\Modules\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Events\StockBecameLow;
use App\Modules\Commerce\Inventory\Events\StockDepleted;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Exclusively owns locked stock projections and matching append-only movements.
 */
final readonly class InventoryService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /**
     * Post a signed manual adjustment and reconcile its projection atomically.
     */
    public function adjust(Product $product, int $quantityDelta, string $note, User $actor): StockMovement
    {
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException('Stock adjustments cannot be zero.');
        }

        $note = trim($note);
        if ($note === '' || strlen($note) > 255) {
            throw new InvalidArgumentException('Stock adjustments require a reason of 255 characters or fewer.');
        }

        return $this->database->transaction(function () use ($product, $quantityDelta, $note, $actor): StockMovement {
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $stock = $this->lockedStock($product, $actor);
            $movement = $this->applyMovement(
                product: $product,
                stock: $stock,
                quantityDelta: $quantityDelta,
                type: $quantityDelta > 0 ? StockMovementType::AdjustmentIn : StockMovementType::AdjustmentOut,
                note: $note,
                actor: $actor,
            );

            $this->activities->record(
                activityType: 'commerce.stock.adjusted',
                description: "Stock adjusted for {$product->name}",
                actor: $actor,
                subject: $movement,
                properties: [
                    'product_ulid' => $product->ulid,
                    'quantity_delta' => $quantityDelta,
                    'balance_before' => $movement->balance_before,
                    'balance_after' => $movement->balance_after,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'commerce-inventory',
            );

            return $movement;
        });
    }

    /**
     * Deduct all tracked order lines and append matching ledger rows once.
     *
     * @return Collection<int, StockMovement>
     */
    public function commitOrder(Order $order, ?User $actor = null): Collection
    {
        return $this->database->transaction(function () use ($order, $actor): Collection {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($order->stock_committed_at !== null) {
                throw new InvalidOrderTransitionException('Order stock has already been committed.');
            }

            $items = OrderItem::query()->whereBelongsTo($order)->orderBy('product_id')->get();
            if ($items->isEmpty()) {
                throw new InvalidOrderTransitionException('Stock cannot be committed for an order without items.');
            }

            $movements = collect();
            foreach ($items as $item) {
                $product = Product::withTrashed()->lockForUpdate()->findOrFail($item->product_id);
                if (! $product->track_stock) {
                    continue;
                }

                $stock = $this->lockedStock($product, $actor);
                $movements->push($this->applyMovement(
                    product: $product,
                    stock: $stock,
                    quantityDelta: -$item->quantity,
                    type: StockMovementType::OrderCommit,
                    note: "Committed for order {$order->order_number}",
                    actor: $actor,
                    reference: $order,
                ));
            }

            $order->forceFill(['stock_committed_at' => now()])->save();

            return $movements;
        });
    }

    /**
     * Restore previously committed stock through compensating movements once.
     *
     * @return Collection<int, StockMovement>
     */
    public function releaseOrder(Order $order, ?User $actor = null): Collection
    {
        return $this->database->transaction(function () use ($order, $actor): Collection {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($order->stock_committed_at === null || $order->stock_released_at !== null) {
                throw new InvalidOrderTransitionException('Order stock is not eligible for release.');
            }

            $items = OrderItem::query()->whereBelongsTo($order)->orderBy('product_id')->get();
            $movements = collect();

            foreach ($items as $item) {
                $product = Product::withTrashed()->lockForUpdate()->findOrFail($item->product_id);
                if (! $product->track_stock) {
                    continue;
                }

                $stock = $this->lockedStock($product, $actor);
                $movements->push($this->applyMovement(
                    product: $product,
                    stock: $stock,
                    quantityDelta: $item->quantity,
                    type: StockMovementType::OrderCancel,
                    note: "Restored from cancelled order {$order->order_number}",
                    actor: $actor,
                    reference: $order,
                ));
            }

            $order->forceFill(['stock_released_at' => now()])->save();

            return $movements;
        });
    }

    private function lockedStock(Product $product, ?User $actor): Stock
    {
        $stock = Stock::query()->where('product_id', $product->getKey())->lockForUpdate()->first();
        if ($stock instanceof Stock) {
            return $stock;
        }

        $stock = new Stock;
        $stock->forceFill([
            'product_id' => $product->getKey(),
            'on_hand' => 0,
            'low_stock_threshold' => (int) config('commerce.inventory.default_low_stock_threshold', 5),
            'updated_by' => $actor?->getKey(),
        ])->save();

        return $stock;
    }

    private function applyMovement(
        Product $product,
        Stock $stock,
        int $quantityDelta,
        StockMovementType $type,
        string $note,
        ?User $actor,
        ?Order $reference = null,
    ): StockMovement {
        $before = $stock->on_hand;
        $after = $before + $quantityDelta;

        if ($after < 0 && ! (bool) config('commerce.inventory.allow_oversell', false)) {
            throw InsufficientStockException::forProduct($product, abs($quantityDelta), $before);
        }

        $stock->forceFill([
            'on_hand' => $after,
            'updated_by' => $actor?->getKey(),
        ])->save();

        $movement = new StockMovement;
        $movement->forceFill([
            'product_id' => $product->getKey(),
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'created_by' => $actor?->getKey(),
            'created_at' => now(),
        ])->save();

        $this->dispatchStockTransition($product, $stock, $before, $after);

        return $movement;
    }

    /**
     * Emit one state-transition alert while the stock projection remains locked.
     */
    private function dispatchStockTransition(Product $product, Stock $stock, int $before, int $after): void
    {
        if ($before > 0 && $after <= 0) {
            StockDepleted::dispatch($product->ulid, $before, $after);

            return;
        }

        if ($before > $stock->low_stock_threshold
            && $after > 0
            && $after <= $stock->low_stock_threshold) {
            StockBecameLow::dispatch(
                $product->ulid,
                $before,
                $after,
                $stock->low_stock_threshold,
            );
        }
    }
}
