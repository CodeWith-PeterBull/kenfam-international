<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Orders\Data\CartCalculation;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Services\CartCalculator;
use App\Modules\Commerce\Storefront\Data\StorefrontCartSnapshot;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * Owns the storefront session cart and stores product IDs plus quantities only.
 */
final readonly class CartSessionService
{
    public function __construct(
        private Session $session,
        private CartCalculator $calculator,
    ) {}

    /**
     * Add a quantity to an existing line after re-fetching public product state.
     */
    public function add(int $productId, int $quantity = 1): StorefrontCartSnapshot
    {
        $items = $this->storedItems();
        $quantity += $items[$productId] ?? 0;
        $product = $this->sellableProduct($productId);
        $this->assertQuantityAvailable($product, $quantity);
        $items[$productId] = $quantity;
        $this->persist($items);

        return $this->snapshot();
    }

    /**
     * Replace one line quantity, or remove it when zero is requested.
     */
    public function setQuantity(int $productId, int $quantity): StorefrontCartSnapshot
    {
        if ($quantity <= 0) {
            return $this->remove($productId);
        }

        $items = $this->storedItems();
        if (! array_key_exists($productId, $items)) {
            throw new InvalidCartException('The selected product is not present in your cart.');
        }

        $product = $this->sellableProduct($productId);
        $this->assertQuantityAvailable($product, $quantity);
        $items[$productId] = $quantity;
        $this->persist($items);

        return $this->snapshot();
    }

    /**
     * Remove one product without changing any other session line.
     */
    public function remove(int $productId): StorefrontCartSnapshot
    {
        $items = $this->storedItems();
        unset($items[$productId]);
        $this->persist($items);

        return $this->snapshot();
    }

    /**
     * Clear the complete storefront cart after checkout or explicit user action.
     */
    public function clear(): void
    {
        $this->session->forget($this->sessionKey());
    }

    /**
     * Return the current unit count without trusting non-integer session values.
     */
    public function count(): int
    {
        return array_sum($this->storedItems());
    }

    /**
     * Re-fetch, normalize, and calculate current cart state for presentation.
     */
    public function snapshot(FulfillmentType $fulfillment = FulfillmentType::Pickup): StorefrontCartSnapshot
    {
        $stored = $this->storedItems();
        if ($stored === []) {
            return new StorefrontCartSnapshot($this->emptyCalculation(), 0);
        }

        $products = Product::query()
            ->visibleInStorefront()
            ->whereKey(array_keys($stored))
            ->with(['category', 'stock', 'media'])
            ->get()
            ->keyBy(static fn (Product $product): int => $product->getKey());
        $issues = [];
        $normalized = [];

        foreach ($stored as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                $issues[] = 'A product that is no longer available was removed from your cart.';

                continue;
            }

            $quantity = max($product->minimum_order_quantity, $quantity);
            if ($product->maximum_order_quantity !== null) {
                $quantity = min($product->maximum_order_quantity, $quantity);
            }
            $normalized[$productId] = $quantity;

            if (! $this->canSupply($product, $quantity)) {
                $available = max(0, (int) ($product->stock?->on_hand ?? 0));
                $issues[] = "{$product->name} currently has {$available} available.";
            }
        }

        if ($normalized !== $stored) {
            $this->persist($normalized);
        }

        if ($normalized === []) {
            return new StorefrontCartSnapshot($this->emptyCalculation(), 0, array_values(array_unique($issues)));
        }

        $items = array_map(
            static fn (int $quantity, int $productId): CartItemData => new CartItemData($productId, $quantity),
            array_values($normalized),
            array_keys($normalized),
        );
        $data = new OrderPlacementData(
            items: $items,
            customer: new CustomerSnapshotData(
                firstName: 'Cart',
                lastName: 'Preview',
                addressLine1: $fulfillment === FulfillmentType::Delivery ? 'Provided during checkout' : null,
                city: $fulfillment === FulfillmentType::Delivery ? 'Provided during checkout' : null,
                countryCode: (string) config('commerce.checkout.country_code', 'KE'),
            ),
            fulfillmentType: $fulfillment,
            deliveryFeeMinor: $this->deliveryFee($fulfillment),
        );

        /** @var Collection<int, Product> $calculationProducts */
        $calculationProducts = $products->only(array_keys($normalized))->values();
        $calculation = $this->calculator->calculate($data, $calculationProducts);

        return new StorefrontCartSnapshot(
            calculation: $calculation,
            itemCount: array_sum($normalized),
            issues: array_values(array_unique($issues)),
        );
    }

    /**
     * Return the configured delivery fee for an eligible fulfillment mode.
     */
    public function deliveryFee(FulfillmentType $fulfillment): int
    {
        return $fulfillment === FulfillmentType::Delivery
            ? max(0, (int) config('commerce.checkout.flat_delivery_fee_minor', 0))
            : 0;
    }

    private function sellableProduct(int $productId): Product
    {
        $product = Product::query()
            ->visibleInStorefront()
            ->with('stock')
            ->whereKey($productId)
            ->first();

        if (! $product instanceof Product) {
            throw new InvalidCartException('The selected product is no longer available.');
        }

        return $product;
    }

    private function assertQuantityAvailable(Product $product, int $quantity): void
    {
        if ($quantity < $product->minimum_order_quantity) {
            throw new InvalidCartException("{$product->name} requires at least {$product->minimum_order_quantity} units.");
        }
        if ($product->maximum_order_quantity !== null && $quantity > $product->maximum_order_quantity) {
            throw new InvalidCartException("{$product->name} allows at most {$product->maximum_order_quantity} units per order.");
        }
        if (! $this->canSupply($product, $quantity)) {
            throw new InvalidCartException('Only '.max(0, (int) ($product->stock?->on_hand ?? 0))." units of {$product->name} are currently available.");
        }
    }

    private function canSupply(Product $product, int $quantity): bool
    {
        return (bool) config('commerce.inventory.allow_oversell', false)
            || ! $product->track_stock
            || (int) ($product->stock?->on_hand ?? 0) >= $quantity;
    }

    /**
     * @return array<int, int>
     */
    private function storedItems(): array
    {
        $stored = $this->session->get($this->sessionKey(), []);
        if (! is_array($stored)) {
            $this->clear();

            return [];
        }

        $sanitized = [];
        foreach ($stored as $productId => $quantity) {
            if ((is_int($productId) || ctype_digit((string) $productId))
                && (is_int($quantity) || ctype_digit((string) $quantity))
                && (int) $productId > 0
                && (int) $quantity > 0) {
                $sanitized[(int) $productId] = (int) $quantity;
            }
        }
        ksort($sanitized);

        if ($sanitized !== $stored) {
            $this->persist($sanitized);
        }

        return $sanitized;
    }

    /**
     * @param  array<int, int>  $items
     */
    private function persist(array $items): void
    {
        if ($items === []) {
            $this->clear();

            return;
        }

        ksort($items);
        $this->session->put($this->sessionKey(), $items);
    }

    private function sessionKey(): string
    {
        return (string) config('commerce.storefront.cart_session_key', 'commerce.storefront.cart');
    }

    private function emptyCalculation(): CartCalculation
    {
        return new CartCalculation(
            lines: [],
            currency: strtoupper((string) config('commerce.currency.code', 'KES')),
            subtotalMinor: 0,
            discountMinor: 0,
            deliveryFeeMinor: 0,
            taxMinor: 0,
            totalMinor: 0,
            taxInclusive: (bool) config('commerce.tax.prices_include_tax', true),
        );
    }
}
