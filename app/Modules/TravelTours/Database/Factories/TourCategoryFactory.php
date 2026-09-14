<?php

/** Define reusable isolated data for a travel catalog category. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\TourCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TourCategory> */
final class TourCategoryFactory extends Factory
{
    protected $model = TourCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['parent_id' => null, 'name' => Str::title($name), 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###')];
    }
}
