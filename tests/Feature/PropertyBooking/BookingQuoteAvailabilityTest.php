<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Availability\Services\AvailabilityBlockService;
use App\Modules\PropertyBooking\Availability\Services\AvailabilitySearchService;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies advisory quoting and lock-backed concrete-unit blocking. */
final class BookingQuoteAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** Restore the global Carbon clock after each deterministic quote test. */
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Confirm quotes expire, count exact units, calculate rates, and mutate nothing. */
    public function test_quote_is_expiring_side_effect_free_and_based_on_current_concrete_availability(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 06:00:00', 'UTC'));
        config()->set('property-booking.booking.quote_minutes', 10);
        [$property, $unitType, $ratePlan, $units] = $this->inventory(2);
        $startsAt = CarbonImmutable::parse('2026-09-10 14:00:00', $property->timezone)->utc();
        $endsAt = CarbonImmutable::parse('2026-09-12 11:00:00', $property->timezone)->utc();

        $quote = app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $endsAt, 2, 0);

        $this->assertSame(2, $quote->availableUnitCount);
        $this->assertSame(2, $quote->calculation->billableUnits);
        $this->assertSame(240_000, $quote->calculation->totalMinor);
        $this->assertSame('2026-08-30 06:10:00', $quote->expiresAt->format('Y-m-d H:i:s'));
        $this->assertFalse($quote->isExpired(CarbonImmutable::parse('2026-08-30 06:09:59', 'UTC')));
        $this->assertTrue($quote->isExpired(CarbonImmutable::parse('2026-08-30 06:10:00', 'UTC')));
        $this->assertDatabaseCount((new Booking)->getTable(), 0);
        $this->assertDatabaseCount((new UnitAssignment)->getTable(), 0);
        $this->assertDatabaseCount((new AvailabilityBlock)->getTable(), 0);

        app(AvailabilityBlockService::class)->create(
            $units[0],
            AvailabilityBlockType::Maintenance,
            $startsAt,
            $endsAt,
            'Scheduled air-conditioning service',
            User::factory()->create(),
        );

        $recalculated = app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $endsAt, 2, 0);
        $this->assertSame(1, $recalculated->availableUnitCount);

        app(AvailabilityBlockService::class)->create(
            $units[1],
            AvailabilityBlockType::OwnerUse,
            $startsAt,
            $endsAt,
            'Reserved for property owner',
            User::factory()->create(),
        );

        $this->expectException(AvailabilityException::class);
        $this->expectExceptionMessage('No concrete unit is available');
        app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $endsAt, 2, 0);
    }

    /** Confirm overlapping writes serialize on the unit and released history stops consuming inventory. */
    public function test_overlapping_blocks_are_rejected_until_the_active_record_is_released(): void
    {
        [$property, $unitType, , $units] = $this->inventory(1);
        $actor = User::factory()->create();
        $service = app(AvailabilityBlockService::class);
        $search = app(AvailabilitySearchService::class);
        $startsAt = CarbonImmutable::now('UTC')->addDays(5);
        $endsAt = $startsAt->addDays(2);
        $block = $service->create(
            $units[0],
            AvailabilityBlockType::DeepCleaning,
            $startsAt,
            $endsAt,
            'Post-renovation deep clean',
            $actor,
        );

        try {
            $service->create(
                $units[0],
                AvailabilityBlockType::Administrative,
                $startsAt->addHour(),
                $endsAt->addHour(),
                'Competing administrative hold',
                $actor,
            );
            $this->fail('A lock-protected overlapping block must be rejected.');
        } catch (AvailabilityException $exception) {
            $this->assertStringContainsString('conflicting', $exception->getMessage());
        }

        $this->assertDatabaseCount((new AvailabilityBlock)->getTable(), 1);
        $this->assertSame(0, $search->availableCount($unitType, $startsAt, $endsAt));

        $released = $service->release($block, $actor);
        $this->assertSame(AvailabilityBlockStatus::Released, $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertSame(1, $search->availableCount($unitType, $startsAt, $endsAt));

        $replacement = $service->create(
            $units[0],
            AvailabilityBlockType::Administrative,
            $startsAt,
            $endsAt,
            'Replacement operational hold',
            $actor,
        );
        $this->assertSame(AvailabilityBlockStatus::Active, $replacement->status);
        $this->assertDatabaseCount((new AvailabilityBlock)->getTable(), 2);
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount): array
    {
        $property = Property::factory()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'turnover_minutes' => 60,
            'minimum_notice_minutes' => 0,
            'maximum_advance_days' => 365,
        ]);
        $unitType = UnitType::factory()->for($property)->create([
            'maximum_guests' => 2,
            'maximum_adults' => 2,
            'maximum_children' => 0,
        ]);
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create([
            'base_rate_minor' => 120_000,
            'included_adults' => 2,
            'included_children' => 0,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 0,
            'tax_rate_bps' => 0,
            'deposit_type' => DepositType::None,
            'deposit_amount_minor' => null,
            'deposit_rate_bps' => null,
        ]);
        $units = AccommodationUnit::factory()->count($unitCount)->forUnitType($unitType)->create()->all();

        return [$property, $unitType, $ratePlan, $units];
    }
}
