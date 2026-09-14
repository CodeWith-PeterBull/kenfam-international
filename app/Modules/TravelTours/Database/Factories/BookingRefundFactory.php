<?php

/** Define an idempotent pending booking refund request. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\RefundStatus;
use App\Modules\TravelTours\Bookings\Models\BookingRefund;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingRefund> */
final class BookingRefundFactory extends Factory
{
    protected $model = BookingRefund::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'operation_key' => 'refund-'.fake()->unique()->uuid(),
            'reference' => 'REF-'.fake()->unique()->numerify('########'),
            'amount_minor' => 5_000_00,
            'currency' => 'KES',
            'reason' => 'Fixture refund request.',
            'status' => RefundStatus::Pending,
            'requested_at' => now(),
            'safe_metadata' => ['source' => 'fixture'],
        ];
    }
}
