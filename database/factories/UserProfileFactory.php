<?php

namespace Database\Factories;

use App\Enums\IdentificationType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserProfile> */
class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-70 years', '-18 years'),
            'identification_type' => fake()->randomElement(IdentificationType::cases()),
            'identification_number' => fake()->unique()->bothify('ID-########'),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'job_title' => fake()->optional()->jobTitle(),
            'bio' => fake()->optional()->paragraph(),
        ];
    }
}
