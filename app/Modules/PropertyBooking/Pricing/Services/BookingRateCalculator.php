<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Services;

use App\Modules\PropertyBooking\Contracts\CalculatesBookingRates;
use App\Modules\PropertyBooking\Pricing\Data\BookingRateCalculation;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Calculates deterministic short-stay prices, restrictions, tax, and deposit. */
final class BookingRateCalculator implements CalculatesBookingRates
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
    ): BookingRateCalculation {
        $ratePlan->loadMissing(['property', 'unitType']);
        $this->assertEligible($ratePlan, $startsAt, $endsAt, $adults, $children, $infants, $advanceNoticePolicy);

        $timezone = $ratePlan->property->timezone;
        $startsLocal = $startsAt->setTimezone($timezone);
        $endsLocal = $endsAt->setTimezone($timezone);
        $billableDates = $this->billableDates($ratePlan->pricing_unit, $startsLocal, $endsLocal);
        $overrides = $this->overrides($ratePlan, $startsLocal, $endsLocal);
        $this->assertRestrictions($ratePlan, $overrides, $billableDates, $startsLocal, $endsLocal);

        $rates = [];
        $baseSubtotal = 0;
        foreach ($billableDates as $date) {
            $override = $this->overrideForDate($overrides, $date);
            $rate = (int) ($override?->rate_minor ?? $ratePlan->base_rate_minor);
            $baseSubtotal = MoneyMath::add($baseSubtotal, $rate);
            $rates[] = ['date' => $date->toDateString(), 'rate_minor' => $rate];
        }

        $extraPerUnit = MoneyMath::add(
            MoneyMath::multiply(max($adults - $ratePlan->included_adults, 0), $ratePlan->extra_adult_minor),
            MoneyMath::multiply(max($children - $ratePlan->included_children, 0), $ratePlan->extra_child_minor),
        );
        $extraGuestMinor = MoneyMath::multiply($extraPerUnit, count($billableDates));
        $subtotal = MoneyMath::add($baseSubtotal, $extraGuestMinor);
        $tax = MoneyMath::percentage($subtotal, $ratePlan->tax_rate_bps, $ratePlan->is_tax_inclusive);
        $total = $ratePlan->is_tax_inclusive ? $subtotal : MoneyMath::add($subtotal, $tax);
        $deposit = match ($ratePlan->deposit_type) {
            DepositType::None => 0,
            DepositType::Fixed => min((int) $ratePlan->deposit_amount_minor, $total),
            DepositType::Percentage => MoneyMath::percentage($total, (int) $ratePlan->deposit_rate_bps),
        };

        return new BookingRateCalculation(
            ratePlanId: $ratePlan->getKey(),
            unitTypeId: $ratePlan->unit_type_id,
            pricingUnit: $ratePlan->pricing_unit,
            currency: $ratePlan->currency,
            billableUnits: count($billableDates),
            unitRateMinor: $rates[0]['rate_minor'],
            rateBreakdown: $rates,
            extraGuestMinor: $extraGuestMinor,
            subtotalMinor: $subtotal,
            taxRateBps: $ratePlan->tax_rate_bps,
            taxMinor: $tax,
            totalMinor: $total,
            requiredDepositMinor: $deposit,
            taxInclusive: $ratePlan->is_tax_inclusive,
        );
    }

    /** Assert that the rate plan can price the requested stay. */
    private function assertEligible(
        RatePlan $ratePlan,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $adults,
        int $children,
        int $infants,
        AdvanceNoticePolicy $advanceNoticePolicy,
    ): void {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new RateConfigurationException('A stay must end after it starts.');
        }
        if ($ratePlan->status !== RatePlanStatus::Active) {
            throw new RateConfigurationException('Only an active rate plan can be booked.');
        }
        if ($ratePlan->property_id !== $ratePlan->unitType->property_id) {
            throw new RateConfigurationException('The rate plan and unit type must belong to the same property.');
        }
        if (strtoupper($ratePlan->currency) !== strtoupper($ratePlan->property->currency)) {
            throw new RateConfigurationException('The rate currency must match the property currency.');
        }
        if ($adults < 1 || $children < 0 || $infants < 0) {
            throw new RateConfigurationException('A stay requires one adult and non-negative occupant counts.');
        }
        $unitType = $ratePlan->unitType;
        if ($adults > $unitType->maximum_adults
            || $children > $unitType->maximum_children
            || $infants > $unitType->maximum_infants
            || $adults + $children > $unitType->maximum_guests) {
            throw new RateConfigurationException('The requested occupants exceed this accommodation type capacity.');
        }

        $now = CarbonImmutable::now('UTC');
        if ($advanceNoticePolicy->isEnforced()) {
            $minimumAdvance = max($ratePlan->minimum_advance_minutes, $ratePlan->property->minimum_notice_minutes);
            if ($startsAt->lessThan($now->addMinutes($minimumAdvance))) {
                throw new RateConfigurationException('The stay begins before the minimum booking notice window.');
            }
        } else {
            $pastGrace = max(1, min(1_440, (int) config('property-booking.pob.walk_in_past_grace_minutes', 15)));
            if ($startsAt->lessThan($now->subMinutes($pastGrace)->startOfMinute())) {
                throw new RateConfigurationException('An onsite stay cannot begin before the walk-in entry window.');
            }
        }
        $maximumDays = $ratePlan->maximum_advance_days ?? $ratePlan->property->maximum_advance_days;
        if ($maximumDays !== null && $startsAt->greaterThan($now->addDays($maximumDays))) {
            throw new RateConfigurationException('The stay begins beyond the maximum advance-booking window.');
        }

        $this->assertDepositConfiguration($ratePlan);
    }

    /** Assert that the configured deposit rule is internally consistent. */
    private function assertDepositConfiguration(RatePlan $ratePlan): void
    {
        $valid = match ($ratePlan->deposit_type) {
            DepositType::None => $ratePlan->deposit_amount_minor === null && $ratePlan->deposit_rate_bps === null,
            DepositType::Fixed => $ratePlan->deposit_amount_minor !== null && $ratePlan->deposit_rate_bps === null,
            DepositType::Percentage => $ratePlan->deposit_amount_minor === null
                && $ratePlan->deposit_rate_bps !== null
                && $ratePlan->deposit_rate_bps <= 10_000,
        };

        if (! $valid) {
            throw new RateConfigurationException('The rate plan deposit configuration is inconsistent.');
        }
    }

    /** @return list<CarbonImmutable> */
    private function billableDates(
        StayPricingUnit $unit,
        CarbonImmutable $startsLocal,
        CarbonImmutable $endsLocal,
    ): array {
        if ($unit === StayPricingUnit::Hour) {
            $seconds = (int) ceil($startsLocal->diffInSeconds($endsLocal));
            $count = max(1, (int) ceil($seconds / 3600));

            return array_map(
                static fn (int $offset): CarbonImmutable => $startsLocal->addHours($offset)->startOfDay(),
                range(0, $count - 1),
            );
        }

        $startDate = $startsLocal->startOfDay();
        $endDate = $endsLocal->startOfDay();
        $calendarDays = (int) $startDate->diffInDays($endDate);
        if ($unit === StayPricingUnit::Night && $calendarDays < 1) {
            throw new RateConfigurationException('A nightly stay must depart on a later local date.');
        }

        $count = max(1, $calendarDays);

        return array_map(
            static fn (int $offset): CarbonImmutable => $startDate->addDays($offset),
            range(0, $count - 1),
        );
    }

    /** @return Collection<int, RateOverride> */
    private function overrides(RatePlan $ratePlan, CarbonImmutable $startsLocal, CarbonImmutable $endsLocal): Collection
    {
        $queryEnd = $endsLocal->startOfDay();
        if ($queryEnd->lessThanOrEqualTo($startsLocal->startOfDay())) {
            $queryEnd = $startsLocal->startOfDay()->addDay();
        }

        return $ratePlan->overrides()
            ->where('starts_on', '<', $queryEnd->addDay()->toDateString())
            ->where('ends_on', '>', $startsLocal->startOfDay()->toDateString())
            ->get();
    }

    /** @param Collection<int, RateOverride> $overrides @param list<CarbonImmutable> $billableDates */
    private function assertRestrictions(
        RatePlan $ratePlan,
        Collection $overrides,
        array $billableDates,
        CarbonImmutable $startsLocal,
        CarbonImmutable $endsLocal,
    ): void {
        foreach ($billableDates as $date) {
            if ($this->overrideForDate($overrides, $date)?->is_closed === true) {
                throw new RateConfigurationException('The selected rate is closed for one or more requested dates.');
            }
        }

        $arrivalOverride = $this->overrideForDate($overrides, $startsLocal->startOfDay());
        $departureOverride = $this->overrideForDate($overrides, $endsLocal->startOfDay());
        if ($arrivalOverride?->closed_on_arrival === true) {
            throw new RateConfigurationException('The selected rate is closed for arrival on this date.');
        }
        if ($departureOverride?->closed_on_departure === true) {
            throw new RateConfigurationException('The selected rate is closed for departure on this date.');
        }

        $minimum = $ratePlan->minimum_units;
        $maximum = $ratePlan->maximum_units;
        foreach ($overrides as $override) {
            $minimum = max($minimum, $override->minimum_units ?? $minimum);
            if ($override->maximum_units !== null) {
                $maximum = $maximum === null ? $override->maximum_units : min($maximum, $override->maximum_units);
            }
        }

        $units = count($billableDates);
        if ($units < $minimum || ($maximum !== null && $units > $maximum)) {
            throw new RateConfigurationException('The requested duration is outside the selected rate restrictions.');
        }
    }

    /** @param Collection<int, RateOverride> $overrides */
    private function overrideForDate(Collection $overrides, CarbonImmutable $date): ?RateOverride
    {
        $value = $date->toDateString();

        return $overrides->first(static fn (RateOverride $override): bool => $override->starts_on->toDateString() <= $value && $override->ends_on->toDateString() > $value
        );
    }
}
