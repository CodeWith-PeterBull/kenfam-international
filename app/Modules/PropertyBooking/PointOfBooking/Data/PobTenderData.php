<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Data;

use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;

/** Immutable operator tender submitted to the authoritative payment service. */
final readonly class PobTenderData
{
    /** Create one exact minor-unit tender without browser-calculated totals. */
    public function __construct(
        public BookingPaymentMethod $method,
        public int $amountMinor,
        public ?int $tenderedMinor = null,
        public ?string $reference = null,
    ) {}
}
