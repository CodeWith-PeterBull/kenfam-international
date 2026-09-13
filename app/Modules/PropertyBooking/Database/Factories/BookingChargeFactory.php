<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeType;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingCharge> */
final class BookingChargeFactory extends Factory
{
    protected $model = BookingCharge::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'booking_stay_id' => null,
            'reception_shift_id' => null,
            'type' => BookingChargeType::Service,
            'status' => BookingChargeStatus::Posted,
            'description' => 'Airport transfer',
            'quantity' => 1,
            'unit_amount_minor' => 250_000,
            'subtotal_minor' => 250_000,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => 250_000,
            'is_tax_inclusive' => true,
            'posted_by' => User::factory(),
            'posted_at' => now(),
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }
}
