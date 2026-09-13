<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;

/**
 * Server-owned order placement payload containing no client price or total fields.
 */
final readonly class OrderPlacementData
{
    /**
     * @param  list<CartItemData>  $items
     */
    public function __construct(
        public array $items,
        public CustomerSnapshotData $customer,
        public FulfillmentType $fulfillmentType,
        public ?PaymentMethod $preferredPaymentMethod = null,
        public int $discountMinor = 0,
        public ?string $discountReason = null,
        public int $deliveryFeeMinor = 0,
        public ?string $customerNote = null,
        public ?string $internalNote = null,
    ) {
        if ($items === []) {
            throw new InvalidCartException('An order must contain at least one product.');
        }

        foreach ($items as $item) {
            if (! $item instanceof CartItemData) {
                throw new InvalidCartException('Order items must use CartItemData values.');
            }
        }

        $productIds = array_map(static fn (CartItemData $item): int => $item->productId, $items);
        if (count($productIds) !== count(array_unique($productIds))) {
            throw new InvalidCartException('Duplicate products must be combined before checkout.');
        }

        if ($discountMinor < 0 || $deliveryFeeMinor < 0) {
            throw new InvalidCartException('Discount and delivery values cannot be negative.');
        }

        if ($fulfillmentType !== FulfillmentType::Delivery && $deliveryFeeMinor !== 0) {
            throw new InvalidCartException('Delivery fees may only be applied to delivery orders.');
        }

        if ($fulfillmentType === FulfillmentType::Delivery
            && ($customer->addressLine1 === null || $customer->city === null)) {
            throw new InvalidCartException('Delivery orders require an address and city.');
        }
    }
}
