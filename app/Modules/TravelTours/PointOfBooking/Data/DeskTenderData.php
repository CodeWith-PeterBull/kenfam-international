<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data;

use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use InvalidArgumentException;

/** One exact minor-unit tender the operator took at the desk; totals never come from the browser. */
final readonly class DeskTenderData
{
    /** Create the tender; cash carries what was handed over so change can be shown on the receipt. */
    public function __construct(
        public PaymentMethod $method,
        public int $amountMinor,
        public ?int $tenderedMinor = null,
        public ?string $reference = null,
    ) {
        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('A tender must be for more than zero.');
        }
        if ($this->tenderedMinor !== null && $this->tenderedMinor < $this->amountMinor) {
            throw new InvalidArgumentException('Cash tendered cannot be less than the amount applied.');
        }
    }

    /** Cash handed back to the customer for this tender. */
    public function changeMinor(): int
    {
        return $this->tenderedMinor === null ? 0 : $this->tenderedMinor - $this->amountMinor;
    }
}
