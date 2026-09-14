<?php

/** Define an open operator-owned booking-desk shift. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingShift> */
final class BookingShiftFactory extends Factory
{
    protected $model = BookingShift::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'register_id' => BookingRegister::factory(),
            'operator_id' => User::factory(),
            'status' => ShiftStatus::Open,
            'currency' => 'KES',
            'currency_exponent' => 2,
            'opening_float_minor' => 10_000_00,
            'opened_at' => now(),
        ];
    }
}
