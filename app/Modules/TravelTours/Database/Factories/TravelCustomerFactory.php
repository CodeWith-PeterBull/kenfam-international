<?php

/** Define a consent-aware travel customer profile. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Customers\Enums\CustomerStatus;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TravelCustomer> */
final class TravelCustomerFactory extends Factory
{
    protected $model = TravelCustomer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();
        $phone = fake()->unique()->e164PhoneNumber();

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => $email,
            'email_hash' => hash('sha256', mb_strtolower($email)),
            'phone' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'contact_hash_version' => 1,
            'country_code' => 'KE',
            'status' => CustomerStatus::Active,
            'email_consent' => false,
            'sms_consent' => false,
            'whatsapp_consent' => false,
        ];
    }
}
