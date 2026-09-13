<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Filters;

use App\Modules\Commerce\Catalog\Enums\ProductStatus;

/**
 * Normalized product catalogue report filters, mirroring the admin list screen.
 */
final readonly class ProductReportFilters
{
    public function __construct(
        public string $search = '',
        public ?ProductStatus $status = null,
        public ?int $categoryId = null,
    ) {}

    public static function fromInputs(string $search = '', string $status = '', string|int|null $categoryId = null): self
    {
        $category = is_numeric($categoryId) ? (int) $categoryId : 0;

        return new self(
            search: trim($search),
            status: ProductStatus::tryFrom($status),
            categoryId: $category > 0 ? $category : null,
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->status !== null || $this->categoryId !== null;
    }
}
