<?php

/** Define a non-stacking seasonal adjustment for a rate plan. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Enums\PricingRuleType;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PricingRule> */
final class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rate_plan_id' => TourRatePlan::factory(),
            'name' => fake()->words(3, true),
            'rule_type' => PricingRuleType::Seasonal,
            'adjustment_type' => AdjustmentType::Percentage,
            'adjustment_value' => 10_00,
            'priority' => 100,
            'is_stackable' => false,
            'is_active' => true,
        ];
    }
}
