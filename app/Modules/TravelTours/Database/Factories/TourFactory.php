<?php

/** Define reusable isolated data for a tour aggregate. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Enums\TourDifficulty;
use App\Modules\TravelTours\Catalog\Enums\TourType;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tour> */
final class TourFactory extends Factory
{
    protected $model = Tour::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'code' => 'TOUR-'.strtoupper(fake()->unique()->bothify('??####')),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'type' => TourType::Escorted,
            'status' => PublicationStatus::Draft,
            'name' => $name,
            'duration_days' => 5,
            'duration_nights' => 4,
            'difficulty' => TourDifficulty::Easy,
            'booking_mode' => BookingMode::Approval,
            'languages' => ['English'],
            'policy_version' => 'fixture-v1',
        ];
    }
}
