<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Filters;

/**
 * Normalized stock-level report filters, mirroring the inventory list screen.
 *
 * The stock state matches the inventory manager's derived states rather than a
 * stored column: healthy, low, out, or untracked.
 */
final readonly class StockLevelReportFilters
{
    public const STATES = ['healthy', 'low', 'out', 'untracked'];

    public function __construct(
        public string $search = '',
        public ?string $stockState = null,
    ) {}

    public static function fromInputs(string $search = '', string $stockState = ''): self
    {
        return new self(
            search: trim($search),
            stockState: in_array($stockState, self::STATES, true) ? $stockState : null,
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->stockState !== null;
    }
}
