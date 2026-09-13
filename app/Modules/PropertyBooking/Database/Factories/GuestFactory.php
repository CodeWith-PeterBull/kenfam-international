<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Guests\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guest> */
final class GuestFactory extends Factory
{
    protected $model = Guest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'created_by' => null,
            'updated_by' => null,
            'title' => null,
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'alternate_phone' => null,
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'nationality_country_code' => 'KE',
            'identity_type' => null,
            'identity_number_ciphertext' => null,
            'identity_number_hash' => null,
            'identity_country_code' => null,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => 'Nairobi',
            'region' => 'Nairobi',
            'postal_code' => '00100',
            'country_code' => 'KE',
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'note' => null,
        ];
    }
}
