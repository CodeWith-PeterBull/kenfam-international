<?php

/** Define a coherent pending booking with immutable commercial snapshots. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TourBooking> */
final class TourBookingFactory extends Factory
{
    protected $model = TourBooking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->addMonths(2)->startOfDay()->addHours(8);

        return [
            'booking_number' => 'KFI-'.now()->format('Ym').'-'.fake()->unique()->numerify('######'),
            'operation_key' => 'booking-'.fake()->unique()->uuid(),
            'departure_id' => TourDeparture::factory(),
            'tour_id' => static fn (array $attributes): int => (int) TourDeparture::query()->findOrFail($attributes['departure_id'])->tour_id,
            'customer_id' => TravelCustomer::factory(),
            'channel' => BookingChannel::Web,
            'confirmation_mode' => BookingMode::Approval,
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'adult_count' => 1,
            'seat_count' => 1,
            'currency' => 'KES',
            'currency_exponent' => 2,
            'subtotal_minor' => 125_000_00,
            'total_minor' => 125_000_00,
            'deposit_required_minor' => 37_500_00,
            'preferred_payment_method' => PaymentMethod::MobileMoney,
            'customer_name_snapshot' => fake()->name(),
            'tour_name_snapshot' => 'Fixture escorted journey',
            'tour_code_snapshot' => 'TOUR-FIXTURE',
            'departure_timezone_snapshot' => 'Africa/Nairobi',
            'departure_starts_at_snapshot' => $startsAt,
            'departure_ends_at_snapshot' => $startsAt->copy()->addDays(4),
            'policy_version_snapshot' => 'fixture-v1',
            'rate_plan_snapshot' => ['code' => 'STANDARD', 'name' => 'Standard rate'],
            'pricing_snapshot' => ['currency' => 'KES', 'total_minor' => 125_000_00, 'lines' => []],
            'terms_accepted_at' => now(),
            'terms_version' => 'fixture-v1',
            'placed_at' => now(),
        ];
    }
}
