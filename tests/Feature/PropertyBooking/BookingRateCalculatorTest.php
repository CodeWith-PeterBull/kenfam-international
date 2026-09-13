<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Contracts\CalculatesBookingRates;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\RateOverrideService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies integer, local-calendar, override, and restriction rate semantics. */
final class BookingRateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_nightly_rate_calculates_overrides_extras_tax_and_deposit_without_floats(): void
    {
        $property = Property::factory()->create(['minimum_notice_minutes' => 0]);
        $unitType = UnitType::factory()->for($property)->create([
            'maximum_guests' => 4, 'maximum_adults' => 3, 'maximum_children' => 2,
        ]);
        $rate = RatePlan::factory()->forUnitType($unitType)->active()->nightly()->create([
            'base_rate_minor' => 10_000,
            'included_adults' => 1,
            'extra_adult_minor' => 2_000,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => true,
            'deposit_type' => DepositType::Percentage,
            'deposit_amount_minor' => null,
            'deposit_rate_bps' => 5000,
        ]);
        $startsAt = CarbonImmutable::now($property->timezone)->addDays(10)->setTime(14, 0)->utc();
        $endsAt = $startsAt->setTimezone($property->timezone)->addDays(2)->setTime(11, 0)->utc();
        app(RateOverrideService::class)->create($rate, [
            'starts_on' => $startsAt->setTimezone($property->timezone)->addDay()->toDateString(),
            'ends_on' => $startsAt->setTimezone($property->timezone)->addDays(2)->toDateString(),
            'rate_minor' => 15_000,
            'is_closed' => false,
            'closed_on_arrival' => false,
            'closed_on_departure' => false,
        ], User::factory()->create());

        $result = app(CalculatesBookingRates::class)->calculate($rate->refresh(), $startsAt, $endsAt, 2, 0);

        $this->assertSame(2, $result->billableUnits);
        $this->assertSame([10_000, 15_000], array_column($result->rateBreakdown, 'rate_minor'));
        $this->assertSame(4_000, $result->extraGuestMinor);
        $this->assertSame(29_000, $result->subtotalMinor);
        $this->assertSame(4_000, $result->taxMinor);
        $this->assertSame(29_000, $result->totalMinor);
        $this->assertSame(14_500, $result->requiredDepositMinor);
    }

    public function test_hourly_duration_uses_whole_hour_ceiling_and_rejects_capacity_overflow(): void
    {
        $property = Property::factory()->create(['minimum_notice_minutes' => 0]);
        $unitType = UnitType::factory()->for($property)->create(['maximum_guests' => 2, 'maximum_adults' => 2]);
        $rate = RatePlan::factory()->forUnitType($unitType)->active()->hourly()->create([
            'base_rate_minor' => 1_000,
            'minimum_units' => 1,
            'deposit_type' => DepositType::None,
            'deposit_amount_minor' => null,
            'deposit_rate_bps' => null,
        ]);
        $start = CarbonImmutable::now('UTC')->addDays(3);
        $result = app(CalculatesBookingRates::class)->calculate($rate, $start, $start->addHours(2)->addMinutes(1), 1, 0);

        $this->assertSame(3, $result->billableUnits);
        $this->assertSame(3_000, $result->totalMinor);

        $this->expectException(RateConfigurationException::class);
        app(CalculatesBookingRates::class)->calculate($rate, $start, $start->addHours(3), 3, 0);
    }

    /** Permit only an explicit onsite context to waive public lead-time restrictions. */
    public function test_onsite_policy_waives_advance_notice_without_weakening_default_pricing(): void
    {
        $now = CarbonImmutable::parse('2026-09-01 10:37:42', 'Africa/Nairobi');
        CarbonImmutable::setTestNow($now);

        try {
            $property = Property::factory()->create([
                'timezone' => 'Africa/Nairobi',
                'minimum_notice_minutes' => 60,
            ]);
            $unitType = UnitType::factory()->for($property)->create();
            $rate = RatePlan::factory()->forUnitType($unitType)->active()->nightly()->create([
                'minimum_advance_minutes' => 60,
            ]);
            $startsAt = $now->startOfMinute()->utc();
            $endsAt = $now->addDay()->setTime(10, 0)->utc();

            try {
                app(CalculatesBookingRates::class)->calculate($rate, $startsAt, $endsAt, 1, 0);
                $this->fail('Default pricing accepted a stay inside its advance-notice window.');
            } catch (RateConfigurationException $exception) {
                $this->assertStringContainsString('minimum booking notice', $exception->getMessage());
            }

            $calculation = app(CalculatesBookingRates::class)->calculate(
                $rate,
                $startsAt,
                $endsAt,
                1,
                0,
                advanceNoticePolicy: AdvanceNoticePolicy::WaiveForOnSiteBooking,
            );

            $this->assertSame(1, $calculation->billableUnits);
            $this->assertGreaterThan(0, $calculation->totalMinor);

            try {
                app(CalculatesBookingRates::class)->calculate(
                    $rate,
                    $startsAt->subMinutes(16),
                    $endsAt,
                    1,
                    0,
                    advanceNoticePolicy: AdvanceNoticePolicy::WaiveForOnSiteBooking,
                );
                $this->fail('Onsite pricing accepted a stay before its configured walk-in entry window.');
            } catch (RateConfigurationException $exception) {
                $this->assertStringContainsString('walk-in entry window', $exception->getMessage());
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_rate_override_service_rejects_ambiguous_overlaps(): void
    {
        $rate = RatePlan::factory()->active()->create();
        $actor = User::factory()->create();
        $service = app(RateOverrideService::class);
        $service->create($rate, ['starts_on' => '2027-01-01', 'ends_on' => '2027-01-05'], $actor);

        $this->expectException(RateConfigurationException::class);
        $this->expectExceptionMessage('may not overlap');
        $service->create($rate, ['starts_on' => '2027-01-04', 'ends_on' => '2027-01-07'], $actor);
    }
}
