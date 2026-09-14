<?php

/** Define an immutable booking-extra price snapshot. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Models\BookingExtra;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingExtra> */
final class BookingExtraFactory extends Factory
{
    protected $model = BookingExtra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'code_snapshot' => 'EXTRA-'.strtoupper(fake()->unique()->bothify('??##')),
            'name_snapshot' => fake()->words(3, true),
            'pricing_unit_snapshot' => 'per_booking',
            'quantity' => 1,
            'currency' => 'KES',
            'unit_amount_minor' => 5_000_00,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 5_000_00,
        ];
    }
}
