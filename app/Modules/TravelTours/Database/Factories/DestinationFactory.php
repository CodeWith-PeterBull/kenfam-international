<?php

/** Define reusable isolated data for a travel destination. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Destination> */
final class DestinationFactory extends Factory
{
    protected $model = Destination::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'parent_id' => null,
            'type' => DestinationType::City,
            'country_code' => 'KE',
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'timezone' => 'Africa/Nairobi',
            'status' => PublicationStatus::Draft,
        ];
    }
}
