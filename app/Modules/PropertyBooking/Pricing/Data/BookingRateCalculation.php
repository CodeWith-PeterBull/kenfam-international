<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Data;

use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;

/** Complete immutable integer-money result for one booking stay. */
final readonly class BookingRateCalculation
{
    /**
     * @param  list<array{date: string, rate_minor: int}>  $rateBreakdown
     */
    public function __construct(
        public int $ratePlanId,
        public int $unitTypeId,
        public StayPricingUnit $pricingUnit,
        public string $currency,
        public int $billableUnits,
        public int $unitRateMinor,
        public array $rateBreakdown,
        public int $extraGuestMinor,
        public int $subtotalMinor,
        public int $taxRateBps,
        public int $taxMinor,
        public int $totalMinor,
        public int $requiredDepositMinor,
        public bool $taxInclusive,
    ) {}
}
