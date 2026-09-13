<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Contracts;

use App\Modules\PropertyBooking\Pricing\Data\BookingRateCalculation;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Carbon\CarbonImmutable;

/** Calculates trusted accommodation price snapshots without persistence. */
interface CalculatesBookingRates
{
    /** Calculate a deterministic booking-rate projection. */
    public function calculate(
        RatePlan $ratePlan,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $adults,
        int $children,
        int $infants = 0,
        AdvanceNoticePolicy $advanceNoticePolicy = AdvanceNoticePolicy::Enforce,
    ): BookingRateCalculation;
}
