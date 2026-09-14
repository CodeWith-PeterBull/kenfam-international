<?php

/** Define an adult participant price for a rate plan. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ParticipantRate> */
final class ParticipantRateFactory extends Factory
{
    protected $model = ParticipantRate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rate_plan_id' => TourRatePlan::factory(),
            'participant_type' => ParticipantType::Adult,
            'minimum_age' => 18,
            'amount_minor' => 125_000_00,
            'tax_inclusive' => true,
        ];
    }
}
