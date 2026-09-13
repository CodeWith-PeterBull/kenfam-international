<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Data;

use App\Modules\Commerce\Orders\Data\CartCalculation;
use App\Modules\Commerce\Orders\Data\CartItemData;

/**
 * Immutable, server-calculated cart state prepared for storefront rendering.
 */
final readonly class StorefrontCartSnapshot
{
    /**
     * @param  list<string>  $issues
     */
    public function __construct(
        public CartCalculation $calculation,
        public int $itemCount,
        public array $issues = [],
    ) {}

    /**
     * Return whether the cart currently contains no calculated lines.
     */
    public function isEmpty(): bool
    {
        return $this->calculation->lines === [];
    }

    /**
     * Return whether current products, quantities, and stock may enter checkout.
     */
    public function canCheckout(): bool
    {
        return ! $this->isEmpty() && $this->issues === [];
    }

    /**
     * Rebuild the trusted product/quantity payload accepted by OrderService.
     *
     * @return list<CartItemData>
     */
    public function placementItems(): array
    {
        return array_map(
            static fn ($line): CartItemData => new CartItemData($line->product->getKey(), $line->quantity),
            $this->calculation->lines,
        );
    }
}
