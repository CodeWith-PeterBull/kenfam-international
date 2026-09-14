<?php

/** Define an idempotent pending manual booking payment. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPayment> */
final class BookingPaymentFactory extends Factory
{
    protected $model = BookingPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'operation_key' => 'payment-'.fake()->unique()->uuid(),
            'method' => PaymentMethod::MobileMoney,
            'provider' => 'manual',
            'reference' => 'PAY-'.fake()->unique()->numerify('########'),
            'amount_minor' => 37_500_00,
            'currency' => 'KES',
            'status' => PaymentRecordStatus::Pending,
            'safe_metadata' => ['source' => 'fixture'],
        ];
    }
}
