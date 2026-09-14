<?php

/** Define one optional tour extra priced in integer minor units. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourExtra> */
final class TourExtraFactory extends Factory
{
    protected $model = TourExtra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'code' => 'EXTRA-'.strtoupper(fake()->unique()->bothify('??####')),
            'name' => fake()->words(3, true),
            'amount_minor' => 5_000,
            'currency' => 'KES',
            'participant_types' => ['adult', 'child'],
        ];
    }
}
