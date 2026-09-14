<?php

/** Define one active frequently asked question for a tour. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourFaq> */
final class TourFaqFactory extends Factory
{
    protected $model = TourFaq::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tour_id' => Tour::factory(), 'question' => fake()->sentence(), 'answer' => fake()->paragraph(), 'is_active' => true];
    }
}
