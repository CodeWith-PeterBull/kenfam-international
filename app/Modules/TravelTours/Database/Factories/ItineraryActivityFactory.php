<?php

/** Define one scheduled activity within an itinerary day. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\ItineraryActivity;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItineraryActivity> */
final class ItineraryActivityFactory extends Factory
{
    protected $model = ItineraryActivity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'itinerary_day_id' => ItineraryDay::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'is_included' => true,
            'sequence' => 1,
        ];
    }
}
