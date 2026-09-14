<?php

/** Define append-only booking lifecycle history. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Models\BookingStatusHistory;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingStatusHistory> */
final class BookingStatusHistoryFactory extends Factory
{
    protected $model = BookingStatusHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'previous_status' => null,
            'new_status' => BookingStatus::Pending,
            'source' => 'storefront',
            'reason' => 'Initial booking placement.',
            'changed_at' => now(),
        ];
    }
}
