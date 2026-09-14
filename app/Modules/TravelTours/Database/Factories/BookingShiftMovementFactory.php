<?php

/** Define an append-only opening-float movement for a booking shift. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShiftMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingShiftMovement> */
final class BookingShiftMovementFactory extends Factory
{
    protected $model = BookingShiftMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shift_id' => BookingShift::factory(),
            'operation_key' => 'movement-'.fake()->unique()->uuid(),
            'movement_type' => ShiftMovementType::OpeningFloat,
            'amount_minor' => 10_000_00,
            'currency' => 'KES',
            'actor_id' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
