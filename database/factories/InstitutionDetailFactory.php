<?php

namespace Database\Factories;

use App\Models\InstitutionDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InstitutionDetail> */
class InstitutionDetailFactory extends Factory
{
    protected $model = InstitutionDetail::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'short_name' => fake()->lexify('AUR???'),
            'descriptor' => fake()->sentence(6),
            'primary_email' => fake()->companyEmail(),
            'secondary_email' => fake()->safeEmail(),
            'primary_phone' => fake()->phoneNumber(),
            'secondary_phone' => null,
            'website' => fake()->url(),
            'physical_address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'county' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'postal_address' => null,
            'postal_city' => null,
            'social_media' => [
                ['platform' => 'LinkedIn', 'handle' => '@aureon', 'url' => 'https://www.linkedin.com/company/aureon'],
            ],
        ];
    }
}
