<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Data;

use App\Modules\Commerce\Orders\Data\CartCalculation;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;

/**
 * Immutable trusted preview boundary for the Livewire cashier terminal.
 */
final readonly class PosTerminalSnapshot
{
    /**
     * @param  list<string>  $issues
     */
    public function __construct(
        public ?OrderPlacementData $placement,
        public ?CartCalculation $calculation,
        public array $issues = [],
    ) {}

    public function itemCount(): int
    {
        return $this->calculation === null
            ? 0
            : array_sum(array_map(static fn ($line): int => $line->quantity, $this->calculation->lines));
    }

    public function canTransact(): bool
    {
        return $this->placement !== null
            && $this->calculation !== null
            && $this->calculation->lines !== []
            && $this->issues === [];
    }
}
