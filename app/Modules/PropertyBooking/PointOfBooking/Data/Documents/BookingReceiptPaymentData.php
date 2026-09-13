<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Data\Documents;

/** Immutable sanitized tender row rendered by every POB receipt format. */
final readonly class BookingReceiptPaymentData
{
    /** Create one completed payment projection. */
    public function __construct(
        public string $methodLabel,
        public ?string $reference,
        public int $amountMinor,
        public int $changeMinor,
    ) {}
}
