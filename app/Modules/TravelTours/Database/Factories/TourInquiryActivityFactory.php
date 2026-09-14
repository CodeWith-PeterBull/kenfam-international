<?php

/** Define append-only activity history for one travel inquiry. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Inquiries\Models\TourInquiryActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourInquiryActivity> */
final class TourInquiryActivityFactory extends Factory
{
    protected $model = TourInquiryActivity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inquiry_id' => TourInquiry::factory(),
            'activity_type' => 'note',
            'note' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
