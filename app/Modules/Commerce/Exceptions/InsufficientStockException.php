<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Exceptions;

use App\Modules\Commerce\Catalog\Models\Product;

/**
 * Reports an inventory request that exceeds the locked stock projection.
 */
final class InsufficientStockException extends CommerceException
{
    public function __construct(
        public readonly string $productUlid,
        public readonly string $sku,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Insufficient stock is available for {$sku}. Requested {$requested}; available {$available}.");
    }

    /**
     * Create an exception without exposing internal integer identifiers.
     */
    public static function forProduct(Product $product, int $requested, int $available): self
    {
        return new self($product->ulid, $product->sku, $requested, $available);
    }
}
