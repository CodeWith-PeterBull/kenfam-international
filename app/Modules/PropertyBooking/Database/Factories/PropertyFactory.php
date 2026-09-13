<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Property> */
final class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Residences';

        return [
            'category_id' => PropertyCategory::factory(),
            'created_by' => null,
            'updated_by' => null,
            'code' => 'PRP-'.strtoupper(fake()->unique()->bothify('??####')),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'name' => $name,
            'status' => PropertyStatus::Draft,
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(2, true),
            'house_rules' => 'Valid identification is required at check-in. Quiet hours apply overnight.',
            'cancellation_summary' => 'Cancellation terms depend on the selected rate plan.',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'whatsapp_phone' => null,
            'website_url' => null,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => 'Nairobi',
            'region' => 'Nairobi',
            'postal_code' => '00100',
            'country_code' => config('property-booking.defaults.country_code', 'KE'),
            'latitude' => '-1.2863890',
            'longitude' => '36.8172230',
            'timezone' => config('property-booking.defaults.timezone', 'Africa/Nairobi'),
            'currency' => config('property-booking.defaults.currency', 'KES'),
            'check_in_from' => '14:00:00',
            'check_in_until' => '22:00:00',
            'check_out_from' => '07:00:00',
            'check_out_until' => '11:00:00',
            'minimum_notice_minutes' => 0,
            'maximum_advance_days' => 365,
            'turnover_minutes' => config('property-booking.defaults.turnover_minutes', 60),
            'is_featured' => false,
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => null,
        ];
    }

    /** Configure the factory for the published state. */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => PropertyStatus::Published, 'published_at' => now()]);
    }
}
