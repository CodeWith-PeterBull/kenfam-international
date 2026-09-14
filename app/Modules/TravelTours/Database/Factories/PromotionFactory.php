<?php

/** Define an active booking promotion with bounded usage. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TRAVEL-####')),
            'name' => fake()->words(3, true),
            'adjustment_type' => AdjustmentType::Percentage,
            'adjustment_value' => 5_00,
            'maximum_uses' => 100,
            'maximum_uses_per_customer' => 1,
            'is_active' => true,
        ];
    }
}
