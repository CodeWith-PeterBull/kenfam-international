<?php

/** Define a deterministic base rate plan for one tour. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourRatePlan> */
final class TourRatePlanFactory extends Factory
{
    protected $model = TourRatePlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'code' => 'STANDARD-'.strtoupper(fake()->unique()->bothify('??##')),
            'name' => 'Standard rate',
            'currency' => 'KES',
            'tax_inclusive' => true,
            'deposit_type' => 'percentage',
            'deposit_value' => 30_00,
            'is_refundable' => true,
            'is_active' => true,
            'is_public' => true,
        ];
    }
}
