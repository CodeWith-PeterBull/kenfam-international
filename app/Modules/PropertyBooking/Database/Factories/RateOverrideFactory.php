<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RateOverride> */
final class RateOverrideFactory extends Factory
{
    protected $model = RateOverride::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->addMonths(2)->startOfDay();

        return [
            'rate_plan_id' => RatePlan::factory(),
            'created_by' => null,
            'updated_by' => null,
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(3)->toDateString(),
            'rate_minor' => fake()->numberBetween(750_000, 3_000_000),
            'is_closed' => false,
            'closed_on_arrival' => false,
            'closed_on_departure' => false,
            'minimum_units' => null,
            'maximum_units' => null,
            'reason' => 'Seasonal rate adjustment',
        ];
    }
}
