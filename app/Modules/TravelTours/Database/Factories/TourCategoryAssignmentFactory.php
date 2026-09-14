<?php

/** Define a valid explicit tour-category assignment. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Catalog\Models\TourCategoryAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourCategoryAssignment> */
final class TourCategoryAssignmentFactory extends Factory
{
    protected $model = TourCategoryAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tour_id' => Tour::factory(), 'category_id' => TourCategory::factory(), 'is_primary' => true];
    }
}
