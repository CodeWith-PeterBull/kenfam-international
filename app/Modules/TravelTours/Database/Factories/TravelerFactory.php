<?php

/** Define a reusable traveler identity owned by a customer. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Customers\Models\Traveler;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Traveler> */
final class TravelerFactory extends Factory
{
    protected $model = Traveler::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => TravelCustomer::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-20 years')->format('Y-m-d'),
            'participant_type' => ParticipantType::Adult,
            'nationality_code' => 'KE',
        ];
    }
}
