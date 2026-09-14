<?php

/** Define a dated, bookable tour departure with finite capacity. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourDeparture> */
final class TourDepartureFactory extends Factory
{
    protected $model = TourDeparture::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->addMonths(2)->startOfDay()->addHours(8);

        return [
            'tour_id' => Tour::factory(),
            'code' => 'DEP-'.strtoupper(fake()->unique()->bothify('??####')),
            'timezone' => 'Africa/Nairobi',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(4),
            'booking_mode' => BookingMode::Approval,
            'booking_opens_at' => now()->subDay(),
            'booking_closes_at' => $startsAt->copy()->subDays(2),
            'capacity' => 24,
            'minimum_participants' => 1,
            'waitlist_enabled' => false,
            'status' => DepartureStatus::Open,
        ];
    }
}
