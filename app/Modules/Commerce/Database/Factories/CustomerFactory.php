<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define a Kenyan customer with reusable contact and address data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'created_by' => null,
            'updated_by' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => null,
            'tax_identifier' => null,
            'email' => fake()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'region' => 'Nairobi',
            'postal_code' => fake()->postcode(),
            'country_code' => (string) config('commerce.checkout.country_code', 'KE'),
        ];
    }
}
