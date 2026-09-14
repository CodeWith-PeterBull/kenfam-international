<?php

/** Define an immutable adult participant snapshot for a booking. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Models\BookingParticipant;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingParticipant> */
final class BookingParticipantFactory extends Factory
{
    protected $model = BookingParticipant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => TourBooking::factory(),
            'sequence' => 1,
            'is_lead' => true,
            'participant_type' => ParticipantType::Adult,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-20 years')->format('Y-m-d'),
            'age_at_departure' => 35,
            'consumes_seat' => true,
            'allocated_price_minor' => 125_000_00,
        ];
    }
}
