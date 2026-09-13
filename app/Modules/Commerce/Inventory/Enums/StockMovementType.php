<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Enums;

/**
 * Classifies immutable stock-ledger events.
 */
enum StockMovementType: string
{
    case Opening = 'opening';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case OrderCommit = 'order_commit';
    case OrderCancel = 'order_cancel';

    /**
     * Return whether movements of this type normally increase stock.
     */
    public function increasesStock(): bool
    {
        return match ($this) {
            self::Opening, self::AdjustmentIn, self::OrderCancel => true,
            self::AdjustmentOut, self::OrderCommit => false,
        };
    }

    /**
     * Return the human-readable ledger label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening balance',
            self::AdjustmentIn => 'Adjustment increase',
            self::AdjustmentOut => 'Adjustment decrease',
            self::OrderCommit => 'Order commitment',
            self::OrderCancel => 'Order cancellation',
        };
    }
}
