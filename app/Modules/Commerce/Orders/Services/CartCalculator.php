<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Services;

use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Orders\Data\CalculatedCartLine;
use App\Modules\Commerce\Orders\Data\CartCalculation;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use Illuminate\Support\Collection;

/**
 * Calculates deterministic prices, discounts, tax, and totals using integers only.
 */
final class CartCalculator
{
    /**
     * Recalculate trusted snapshots from freshly fetched products.
     *
     * @param  Collection<int, Product>  $products
     */
    public function calculate(OrderPlacementData $data, Collection $products): CartCalculation
    {
        $products = $products->keyBy(fn (Product $product): int => $product->getKey());
        $rawLines = [];

        foreach ($data->items as $item) {
            $product = $products->get($item->productId);
            if (! $product instanceof Product) {
                throw new InvalidCartException('One or more selected products are no longer available.');
            }

            $this->assertSellable($product, $item);
            $unitPrice = $product->effective_price_minor;
            $subtotal = $this->multiply($unitPrice, $item->quantity);
            $rawLines[] = compact('product', 'item', 'unitPrice', 'subtotal');
        }

        usort($rawLines, static fn (array $left, array $right): int => $left['product']->getKey() <=> $right['product']->getKey());
        $subtotal = 0;
        foreach ($rawLines as $rawLine) {
            $subtotal = $this->add($subtotal, $rawLine['subtotal']);
        }

        if ($data->discountMinor > $subtotal) {
            throw new InvalidCartException('The order discount cannot exceed the merchandise subtotal.');
        }

        $discounts = $this->allocateDiscount($rawLines, $subtotal, $data->discountMinor);
        $lines = [];
        $taxTotal = 0;
        $lineTotal = 0;

        foreach ($rawLines as $index => $rawLine) {
            /** @var Product $product */
            $product = $rawLine['product'];
            /** @var CartItemData $item */
            $item = $rawLine['item'];
            $discount = $discounts[$index];
            $discountedBase = $rawLine['subtotal'] - $discount;
            $tax = $this->tax($discountedBase, $product->tax_rate_bps, $product->is_tax_inclusive);
            $total = $product->is_tax_inclusive ? $discountedBase : $this->add($discountedBase, $tax);

            $lines[] = new CalculatedCartLine(
                product: $product,
                quantity: $item->quantity,
                unitPriceMinor: $rawLine['unitPrice'],
                unitCostMinor: $product->cost_price_minor,
                subtotalMinor: $rawLine['subtotal'],
                discountMinor: $discount,
                taxRateBps: $product->tax_rate_bps,
                isTaxInclusive: $product->is_tax_inclusive,
                taxMinor: $tax,
                totalMinor: $total,
            );
            $taxTotal = $this->add($taxTotal, $tax);
            $lineTotal = $this->add($lineTotal, $total);
        }

        return new CartCalculation(
            lines: $lines,
            currency: strtoupper((string) config('commerce.currency.code', 'KES')),
            subtotalMinor: $subtotal,
            discountMinor: $data->discountMinor,
            deliveryFeeMinor: $data->deliveryFeeMinor,
            taxMinor: $taxTotal,
            totalMinor: $this->add($lineTotal, $data->deliveryFeeMinor),
            taxInclusive: collect($lines)->every(fn (CalculatedCartLine $line): bool => $line->isTaxInclusive),
        );
    }

    private function assertSellable(Product $product, CartItemData $item): void
    {
        if ($product->status !== ProductStatus::Published
            || $product->published_at?->isFuture() === true) {
            throw new InvalidCartException("{$product->name} is not currently available for sale.");
        }

        if ($item->quantity < $product->minimum_order_quantity) {
            throw new InvalidCartException("{$product->name} requires at least {$product->minimum_order_quantity} units.");
        }

        if ($product->maximum_order_quantity !== null
            && $item->quantity > $product->maximum_order_quantity) {
            throw new InvalidCartException("{$product->name} allows at most {$product->maximum_order_quantity} units per order.");
        }
    }

    /**
     * Allocate a fixed discount proportionally and distribute residual cents deterministically.
     *
     * @param  list<array{product: Product, item: CartItemData, unitPrice: int, subtotal: int}>  $lines
     * @return list<int>
     */
    private function allocateDiscount(array $lines, int $subtotal, int $discount): array
    {
        if ($discount === 0) {
            return array_fill(0, count($lines), 0);
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($lines as $index => $line) {
            $numerator = $this->multiply($discount, $line['subtotal']);
            $allocations[$index] = intdiv($numerator, $subtotal);
            $remainders[$index] = $numerator % $subtotal;
            $allocated += $allocations[$index];
        }

        $order = array_keys($lines);
        usort($order, static fn (int $left, int $right): int => $remainders[$right] <=> $remainders[$left]
            ?: $lines[$left]['product']->getKey() <=> $lines[$right]['product']->getKey());

        for ($remaining = $discount - $allocated, $offset = 0; $remaining > 0; $remaining--, $offset++) {
            $allocations[$order[$offset % count($order)]]++;
        }

        ksort($allocations);

        return array_values($allocations);
    }

    private function tax(int $base, int $rateBps, bool $inclusive): int
    {
        if ($rateBps <= 0 || $base === 0) {
            return 0;
        }

        $denominator = $inclusive ? 10_000 + $rateBps : 10_000;
        $numerator = $this->multiply($base, $rateBps);

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function multiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right))) {
            throw new InvalidCartException('The requested cart exceeds supported monetary limits.');
        }

        return $left * $right;
    }

    private function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new InvalidCartException('The requested cart exceeds supported monetary limits.');
        }

        return $left + $right;
    }
}
