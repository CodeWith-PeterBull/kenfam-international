<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Contracts\AllocatesUnits;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies exact allocation, capacity exhaustion, turnover, and release behavior. */
final class UnitAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocator_locks_and_uses_each_ready_unit_at_most_once_per_interval(): void
    {
        [$property, $unitType, $rate, $units] = $this->inventory(2);
        $firstStay = $this->stay($property, $unitType, $rate, 1);
        $secondStay = $this->stay($property, $unitType, $rate, 2);
        $service = app(AllocatesUnits::class);

        $first = $service->allocate($firstStay);
        $second = $service->allocate($secondStay);

        $this->assertSame($units[0]->id, $first->unit_id);
        $this->assertSame($units[1]->id, $second->unit_id);
        $this->assertSame($first->id, $service->allocate($firstStay)->id, 'Allocation must be idempotent for the same stay.');

        $this->expectException(AvailabilityException::class);
        $service->allocate($this->stay($property, $unitType, $rate, 3));
    }

    public function test_release_clears_guard_and_turnover_prevents_an_immediate_following_stay(): void
    {
        [$property, $unitType, $rate] = $this->inventory(1);
        $service = app(AllocatesUnits::class);
        $firstStay = $this->stay($property, $unitType, $rate, 1);
        $assignment = $service->allocate($firstStay);

        $nextBooking = Booking::factory()->forProperty($property)->confirmed()->create([
            'starts_at' => $firstStay->ends_at,
            'ends_at' => $firstStay->ends_at->copy()->addDay(),
        ]);
        $nextStay = BookingStay::factory()->create([
            'booking_id' => $nextBooking->id,
            'unit_type_id' => $unitType->id,
            'rate_plan_id' => $rate->id,
            'line_number' => 1,
            'starts_at' => $nextBooking->starts_at,
            'ends_at' => $nextBooking->ends_at,
            'unit_type_name' => $unitType->name,
            'unit_type_code' => $unitType->code,
            'rate_plan_name' => $rate->name,
            'rate_plan_code' => $rate->code,
        ]);

        try {
            $service->allocate($nextStay);
            $this->fail('An active assignment must retain its post-departure turnover buffer.');
        } catch (AvailabilityException) {
            $this->addToAssertionCount(1);
        }

        $released = $service->release($assignment, 'Guest changed accommodation');
        $this->assertNull($released->active_stay_guard);
        $this->assertNotNull($released->released_at);
        $this->assertNotNull($service->allocate($nextStay), 'A released assignment no longer consumes availability.');
    }

    /** @return array{Property, UnitType, RatePlan, list<AccommodationUnit>} */
    private function inventory(int $unitCount): array
    {
        $property = Property::factory()->create(['turnover_minutes' => 60]);
        $unitType = UnitType::factory()->for($property)->create();
        $rate = RatePlan::factory()->forUnitType($unitType)->active()->create();
        $units = AccommodationUnit::factory()->count($unitCount)->forUnitType($unitType)->create()->all();

        return [$property, $unitType, $rate, $units];
    }

    private function stay(Property $property, UnitType $unitType, RatePlan $rate, int $line): BookingStay
    {
        $booking = Booking::factory()->forProperty($property)->confirmed()->create();

        return BookingStay::factory()->create([
            'booking_id' => $booking->id,
            'unit_type_id' => $unitType->id,
            'rate_plan_id' => $rate->id,
            'line_number' => $line,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'unit_type_name' => $unitType->name,
            'unit_type_code' => $unitType->code,
            'rate_plan_name' => $rate->name,
            'rate_plan_code' => $rate->code,
        ]);
    }
}
