<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Seeders;

use App\Models\User;
use App\Modules\Commerce\Customers\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Seeds reusable demonstration customers shared by web and POS examples.
 */
final class CustomerDemoSeeder extends Seeder
{
    /**
     * Create or refresh stable customer identities without duplicating them.
     */
    public function run(): void
    {
        $actorId = User::query()->where('email', 'admin@aureon.test')->value('id')
            ?? User::query()->value('id');

        foreach ($this->customers() as $attributes) {
            $customer = Customer::withTrashed()->where('email', $attributes['email'])->first() ?? new Customer;
            if ($customer->trashed()) {
                $customer->restore();
            }

            $customer->fill($attributes);
            $customer->forceFill([
                'created_by' => $customer->created_by ?? $actorId,
                'updated_by' => $actorId,
            ])->save();
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function customers(): array
    {
        return [
            [
                'user_id' => null,
                'first_name' => 'Amina',
                'last_name' => 'Njoroge',
                'company' => 'Northstar Studio',
                'tax_identifier' => null,
                'email' => 'amina.njoroge@example.test',
                'phone' => '+254 712 100 101',
                'address_line_1' => '14 Riverside Drive',
                'address_line_2' => null,
                'city' => 'Nairobi',
                'region' => 'Nairobi',
                'postal_code' => '00100',
                'country_code' => 'KE',
            ],
            [
                'user_id' => null,
                'first_name' => 'Brian',
                'last_name' => 'Otieno',
                'company' => null,
                'tax_identifier' => null,
                'email' => 'brian.otieno@example.test',
                'phone' => '+254 723 200 202',
                'address_line_1' => 'Mombasa Road',
                'address_line_2' => 'Block C',
                'city' => 'Nairobi',
                'region' => 'Nairobi',
                'postal_code' => '00506',
                'country_code' => 'KE',
            ],
            [
                'user_id' => null,
                'first_name' => 'Wanjiku',
                'last_name' => 'Kamau',
                'company' => 'Fieldwork Partners',
                'tax_identifier' => 'P051234567X',
                'email' => 'wanjiku.kamau@example.test',
                'phone' => '+254 734 300 303',
                'address_line_1' => 'Kenyatta Avenue',
                'address_line_2' => null,
                'city' => 'Nakuru',
                'region' => 'Nakuru',
                'postal_code' => '20100',
                'country_code' => 'KE',
            ],
        ];
    }
}
