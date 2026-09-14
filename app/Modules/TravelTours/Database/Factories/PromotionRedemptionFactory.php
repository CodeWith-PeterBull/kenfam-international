<?php

/** Define immutable evidence of one promotion redemption. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\PromotionRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromotionRedemption> */
final class PromotionRedemptionFactory extends Factory
{
    protected $model = PromotionRedemption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'booking_id' => TourBooking::factory(),
            'customer_id' => static fn (array $attributes): int => (int) TourBooking::query()->findOrFail($attributes['booking_id'])->customer_id,
            'amount_applied_minor' => 5_000,
            'currency' => 'KES',
            'redeemed_at' => now(),
        ];
    }
}
