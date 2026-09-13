<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Data\Documents;

/** Privacy-safe immutable snapshot of one reserved accommodation line. */
final readonly class BookingDocumentStayData
{
    /** Create a guest-visible stay line. */
    public function __construct(
        public string $unitTypeName,
        public string $ratePlanName,
        public string $pricingUnitLabel,
        public int $billableUnits,
        public int $adults,
        public int $children,
        public int $infants,
        public int $unitRateMinor,
        public int $taxMinor,
        public int $totalMinor,
    ) {}
}
