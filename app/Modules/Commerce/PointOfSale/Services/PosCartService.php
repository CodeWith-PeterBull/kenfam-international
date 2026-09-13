<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Services;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Services\CartCalculator;
use App\Modules\Commerce\PointOfSale\Data\PosTerminalSnapshot;

/**
 * Re-fetches products and builds trusted POS previews from minimal cart state.
 */
final readonly class PosCartService
{
    public function __construct(private CartCalculator $calculator) {}

    /**
     * @param  array<int|string, mixed>  $cart
     */
    public function snapshot(
        array $cart,
        int $discountMinor = 0,
        ?string $discountReason = null,
        ?Customer $customer = null,
        ?string $internalNote = null,
        ?PaymentMethod $preferredPaymentMethod = null,
    ): PosTerminalSnapshot {
        $items = $this->items($cart);
        if ($items === []) {
            return new PosTerminalSnapshot(null, null);
        }

        $products = Product::query()
            ->with(['category', 'stock', 'media'])
            ->whereIn('id', array_map(static fn (CartItemData $item): int => $item->productId, $items))
            ->get();
        $issues = [];

        if ($products->count() !== count($items)) {
            $issues[] = 'One or more cart products are no longer available.';
        }

        foreach ($items as $item) {
            $product = $products->firstWhere('id', $item->productId);
            if (! $product instanceof Product || ! $product->track_stock) {
                continue;
            }

            $available = (int) ($product->stock?->on_hand ?? 0);
            if ($item->quantity > $available) {
                $issues[] = "{$product->name} has {$available} available.";
            }
        }

        try {
            $placement = new OrderPlacementData(
                items: $items,
                customer: $this->customerSnapshot($customer),
                fulfillmentType: FulfillmentType::Counter,
                preferredPaymentMethod: $preferredPaymentMethod,
                discountMinor: $discountMinor,
                discountReason: $discountMinor > 0 ? trim((string) $discountReason) ?: null : null,
                internalNote: trim((string) $internalNote) ?: null,
            );
            $calculation = $this->calculator->calculate($placement, $products);
        } catch (CommerceException $exception) {
            $issues[] = $exception->getMessage();

            return new PosTerminalSnapshot(null, null, array_values(array_unique($issues)));
        }

        return new PosTerminalSnapshot($placement, $calculation, array_values(array_unique($issues)));
    }

    public function customerSnapshot(?Customer $customer): CustomerSnapshotData
    {
        if (! $customer instanceof Customer) {
            return new CustomerSnapshotData(firstName: 'Walk-in', lastName: 'Customer');
        }

        return new CustomerSnapshotData(
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            company: $customer->company,
            taxIdentifier: $customer->tax_identifier,
            email: $customer->email,
            phone: $customer->phone,
            addressLine1: $customer->address_line_1,
            addressLine2: $customer->address_line_2,
            city: $customer->city,
            region: $customer->region,
            postalCode: $customer->postal_code,
            countryCode: $customer->country_code,
        );
    }

    /**
     * @param  array<int|string, mixed>  $cart
     * @return list<CartItemData>
     */
    private function items(array $cart): array
    {
        $items = [];
        foreach ($cart as $productId => $quantity) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
                continue;
            }
            $items[] = new CartItemData($productId, $quantity);
        }

        usort($items, static fn (CartItemData $left, CartItemData $right): int => $left->productId <=> $right->productId);

        return $items;
    }
}
