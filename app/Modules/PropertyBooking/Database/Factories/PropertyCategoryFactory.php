<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PropertyCategory> */
final class PropertyCategoryFactory extends Factory
{
    protected $model = PropertyCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999999),
            'description' => fake()->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** Configure the factory for the inactive state. */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
