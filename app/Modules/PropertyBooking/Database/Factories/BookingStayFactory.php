<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingStay> */
final class BookingStayFactory extends Factory
{
    protected $model = BookingStay::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rate_plan_id' => RatePlan::factory()->active(),
            'unit_type_id' => fn (array $attributes): int => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->unit_type_id,
            'booking_id' => function (array $attributes): int {
                $ratePlan = RatePlan::query()->findOrFail($attributes['rate_plan_id']);

                return Booking::factory()->forProperty($ratePlan->property)->create()->id;
            },
            'line_number' => 1,
            'starts_at' => fn (array $attributes) => Booking::query()->findOrFail($attributes['booking_id'])->starts_at,
            'ends_at' => fn (array $attributes) => Booking::query()->findOrFail($attributes['booking_id'])->ends_at,
            'pricing_unit' => fn (array $attributes) => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->pricing_unit,
            'billable_units' => 1,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'unit_type_name' => fn (array $attributes): string => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->unitType->name,
            'unit_type_code' => fn (array $attributes): string => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->unitType->code,
            'rate_plan_name' => fn (array $attributes): string => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->name,
            'rate_plan_code' => fn (array $attributes): string => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->code,
            'unit_rate_minor' => fn (array $attributes): int => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->base_rate_minor,
            'extra_guest_minor' => 0,
            'subtotal_minor' => fn (array $attributes): int => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->base_rate_minor,
            'discount_minor' => 0,
            'tax_rate_bps' => fn (array $attributes): int => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->tax_rate_bps,
            'tax_minor' => 0,
            'total_minor' => fn (array $attributes): int => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->base_rate_minor,
            'is_tax_inclusive' => fn (array $attributes): bool => RatePlan::query()->findOrFail($attributes['rate_plan_id'])->is_tax_inclusive,
        ];
    }

    /** Associate the stay snapshot with a checked-in booking. */
    public function checkedIn(): static
    {
        return $this->state(function (array $attributes): array {
            $ratePlan = RatePlan::query()->findOrFail($attributes['rate_plan_id']);

            return ['booking_id' => Booking::factory()->forProperty($ratePlan->property)->checkedIn()];
        });
    }
}
