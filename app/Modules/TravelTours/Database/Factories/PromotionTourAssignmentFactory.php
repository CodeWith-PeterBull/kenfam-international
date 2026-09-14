<?php

/** Define a valid promotion-to-tour inclusion assignment. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\PromotionTourAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromotionTourAssignment> */
final class PromotionTourAssignmentFactory extends Factory
{
    protected $model = PromotionTourAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['promotion_id' => Promotion::factory(), 'tour_id' => Tour::factory(), 'is_exclusion' => false];
    }
}
