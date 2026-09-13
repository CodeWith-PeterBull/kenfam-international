<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Amenity> */
final class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = str(fake()->unique()->words(2, true))->title()->toString();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999999),
            'scope' => AmenityScope::Both,
            'description' => fake()->sentence(),
            'icon_key' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
