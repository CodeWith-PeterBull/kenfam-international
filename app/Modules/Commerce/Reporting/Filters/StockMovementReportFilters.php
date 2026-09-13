<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Filters;

use App\Modules\Commerce\Inventory\Enums\StockMovementType;

/**
 * Normalized stock-movement history report filters, mirroring the ledger screen.
 */
final readonly class StockMovementReportFilters
{
    public function __construct(
        public string $search = '',
        public ?StockMovementType $movementType = null,
    ) {}

    public static function fromInputs(string $search = '', string $movementType = ''): self
    {
        return new self(
            search: trim($search),
            movementType: StockMovementType::tryFrom($movementType),
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->movementType !== null;
    }
}
