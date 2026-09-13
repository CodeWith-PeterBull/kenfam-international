<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Services;

use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Availability\Services\AvailabilitySearchService;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Carbon\CarbonImmutable;

/** Produces expiring read-only quotes without allocating or mutating inventory. */
final readonly class BookingQuoteService
{
    /** Create the quote service from authoritative pricing and availability readers. */
    public function __construct(
        private BookingRateCalculator $rates,
        private AvailabilitySearchService $availability,
    ) {}

    /** Generate an advisory quote that placement must later recalculate under locks. */
    public function quote(
        RatePlan $ratePlan,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $adults,
        int $children,
        int $infants = 0,
        AdvanceNoticePolicy $advanceNoticePolicy = AdvanceNoticePolicy::Enforce,
    ): BookingQuote {
        $ratePlan->loadMissing(['property', 'unitType']);
        $calculation = $this->rates->calculate(
            $ratePlan,
            $startsAt,
            $endsAt,
            $adults,
            $children,
            $infants,
            $advanceNoticePolicy,
        );
        $availableCount = $this->availability->availableCount($ratePlan->unitType, $startsAt, $endsAt);
        if ($availableCount < 1) {
            throw new AvailabilityException('No concrete unit is available for the requested interval.');
        }

        $generatedAt = CarbonImmutable::now('UTC');
        $lifetime = max(1, min(60, (int) config('property-booking.booking.quote_minutes', 10)));

        return new BookingQuote(
            propertyId: $ratePlan->property_id,
            propertyUlid: $ratePlan->property->ulid,
            propertyName: $ratePlan->property->name,
            unitTypeId: $ratePlan->unit_type_id,
            unitTypeUlid: $ratePlan->unitType->ulid,
            unitTypeName: $ratePlan->unitType->name,
            ratePlanId: $ratePlan->getKey(),
            ratePlanUlid: $ratePlan->ulid,
            ratePlanName: $ratePlan->name,
            startsAt: $startsAt->utc(),
            endsAt: $endsAt->utc(),
            adults: $adults,
            children: $children,
            infants: $infants,
            availableUnitCount: $availableCount,
            calculation: $calculation,
            generatedAt: $generatedAt,
            expiresAt: $generatedAt->addMinutes($lifetime),
        );
    }
}
