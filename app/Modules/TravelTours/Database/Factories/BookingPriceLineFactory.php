<?php

/** Define a deterministic immutable booking price line. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Models\BookingPriceLine;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPriceLine> */
final class BookingPriceLineFactory extends Factory
{
    protected $model = BookingPriceLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'line_type' => 'participant_fare',
            'description' => 'Adult participant fare',
            'quantity' => 1,
            'unit_amount_minor' => 125_000_00,
            'total_minor' => 125_000_00,
            'calculation_metadata' => ['participant_type' => 'adult'],
        ];
    }
}
