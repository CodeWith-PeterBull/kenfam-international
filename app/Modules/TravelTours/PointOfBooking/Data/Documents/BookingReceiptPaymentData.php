<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data\Documents;

/** One confirmed tender as it appears on a desk receipt. */
final readonly class BookingReceiptPaymentData
{
    /** Create the tender line; change is only ever present on cash. */
    public function __construct(
        public string $methodLabel,
        public ?string $reference,
        public int $amountMinor,
        public int $changeMinor,
    ) {}
}
