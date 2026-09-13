<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Data;

use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;

/** Validated administration tender input; totals remain service authoritative. */
final readonly class AdministrationPaymentData
{
    /** Create one completed non-cash payment request. */
    public function __construct(
        public BookingPaymentMethod $method,
        public int $amountMinor,
        public string $reference,
    ) {}
}
