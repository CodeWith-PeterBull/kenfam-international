<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitAssignment> */
final class UnitAssignmentFactory extends Factory
{
    protected $model = UnitAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_stay_id' => BookingStay::factory(),
            'booking_id' => fn (array $attributes): int => BookingStay::query()->findOrFail($attributes['booking_stay_id'])->booking_id,
            'property_id' => fn (array $attributes): int => BookingStay::query()->findOrFail($attributes['booking_stay_id'])->booking->property_id,
            'unit_id' => function (array $attributes): int {
                $stay = BookingStay::query()->findOrFail($attributes['booking_stay_id']);

                return AccommodationUnit::factory()->forUnitType($stay->unitType)->create()->id;
            },
            'status' => UnitAssignmentStatus::Active,
            'starts_at' => fn (array $attributes) => BookingStay::query()->findOrFail($attributes['booking_stay_id'])->starts_at,
            'ends_at' => fn (array $attributes) => BookingStay::query()->findOrFail($attributes['booking_stay_id'])->ends_at,
            'active_stay_guard' => fn (array $attributes): int => (int) $attributes['booking_stay_id'],
            'assigned_by' => null,
            'assigned_at' => now(),
            'released_by' => null,
            'released_at' => null,
            'release_reason' => null,
        ];
    }

    /** Retain historical assignment while clearing its active uniqueness guard. */
    public function released(): static
    {
        return $this->state(fn (): array => [
            'status' => UnitAssignmentStatus::Released,
            'active_stay_guard' => null,
            'released_by' => null,
            'released_at' => now(),
            'release_reason' => 'Factory release state',
        ]);
    }
}
