<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data\Documents;

/** One priced line on a desk receipt: a fare band, an extra, or an adjustment. */
final readonly class BookingReceiptLineData
{
    /** Create the line from the booking's immutable price snapshot. */
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitMinor,
        public int $totalMinor,
    ) {}
}
