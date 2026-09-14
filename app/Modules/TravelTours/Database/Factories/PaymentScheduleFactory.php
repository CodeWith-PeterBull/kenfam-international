<?php

/** Define the first expected booking payment instalment. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\PaymentScheduleStatus;
use App\Modules\TravelTours\Bookings\Models\PaymentSchedule;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentSchedule> */
final class PaymentScheduleFactory extends Factory
{
    protected $model = PaymentSchedule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'instalment_number' => 1,
            'due_date' => now()->addWeek()->toDateString(),
            'expected_amount_minor' => 37_500_00,
            'paid_amount_minor' => 0,
            'status' => PaymentScheduleStatus::Pending,
        ];
    }
}
