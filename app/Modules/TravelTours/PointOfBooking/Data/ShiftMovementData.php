<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use InvalidArgumentException;

/** One operator-declared cash movement (cash in or cash out) on an open shift. */
final readonly class ShiftMovementData
{
    /** Validate that only manual movement types and positive magnitudes are supplied. */
    public function __construct(
        public string $operationKey,
        public ShiftMovementType $type,
        public int $amountMinor,
        public string $reason,
    ) {
        if (trim($this->operationKey) === '' || mb_strlen($this->operationKey) > 64) {
            throw new InvalidArgumentException('Movement operation key must contain at most 64 characters.');
        }
        if (! in_array($this->type, [ShiftMovementType::CashIn, ShiftMovementType::CashOut], true)) {
            throw new InvalidArgumentException('Only cash in and cash out movements can be declared by an operator.');
        }
        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('Movement amount must be greater than zero.');
        }
        if (trim($this->reason) === '') {
            throw new InvalidArgumentException('A cash movement requires a reason.');
        }
    }
}
