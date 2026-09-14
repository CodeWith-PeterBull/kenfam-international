<?php

/** Define one ordered itinerary day for a tour. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItineraryDay> */
final class ItineraryDayFactory extends Factory
{
    protected $model = ItineraryDay::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'day_number' => 1,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'meals' => ['Breakfast'],
            'sort_order' => 1,
        ];
    }
}
