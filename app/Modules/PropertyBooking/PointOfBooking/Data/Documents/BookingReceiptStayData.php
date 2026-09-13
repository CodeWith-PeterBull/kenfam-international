<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Data\Documents;

/** Privacy-safe immutable stay line rendered by every POB receipt format. */
final readonly class BookingReceiptStayData
{
    /** Create a historical accommodation and rate snapshot. */
    public function __construct(
        public string $unitTypeName,
        public string $ratePlanName,
        public int $billableUnits,
        public string $pricingUnitLabel,
        public int $occupantCount,
        public int $unitRateMinor,
        public int $taxMinor,
        public int $totalMinor,
    ) {}
}
