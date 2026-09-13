<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Data;

use Carbon\CarbonImmutable;

/** Immutable advisory availability and price quote that never reserves a unit. */
final readonly class BookingQuote
{
    /** Create a complete short-lived booking quote projection. */
    public function __construct(
        public int $propertyId,
        public string $propertyUlid,
        public string $propertyName,
        public int $unitTypeId,
        public string $unitTypeUlid,
        public string $unitTypeName,
        public int $ratePlanId,
        public string $ratePlanUlid,
        public string $ratePlanName,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $adults,
        public int $children,
        public int $infants,
        public int $availableUnitCount,
        public BookingRateCalculation $calculation,
        public CarbonImmutable $generatedAt,
        public CarbonImmutable $expiresAt,
    ) {}

    /** Determine whether this advisory quote has passed its feedback lifetime. */
    public function isExpired(?CarbonImmutable $at = null): bool
    {
        return ($at ?? CarbonImmutable::now('UTC'))->greaterThanOrEqualTo($this->expiresAt);
    }
}
