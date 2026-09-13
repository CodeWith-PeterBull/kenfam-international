<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Data;

use App\Modules\Commerce\Exceptions\InvalidCartException;

/**
 * Identifies one requested product and integer quantity without accepting price data.
 */
final readonly class CartItemData
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {
        if ($productId <= 0) {
            throw new InvalidCartException('Cart products must use a valid internal identifier.');
        }

        if ($quantity <= 0) {
            throw new InvalidCartException('Cart quantities must be positive whole numbers.');
        }
    }
}
