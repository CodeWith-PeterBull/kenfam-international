<?php

/** Define a configurable active booking-desk register. */
declare(strict_types=1);

namespace App\Modules\TravelTours\Database\Factories;

use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingRegister> */
final class BookingRegisterFactory extends Factory
{
    protected $model = BookingRegister::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'REG-'.strtoupper(fake()->unique()->bothify('??##')),
            'name' => fake()->unique()->words(2, true).' booking desk',
            'location' => 'Nairobi',
            'is_active' => true,
            'receipt_printer_driver' => 'browser',
            'receipt_paper_width_mm' => 80,
            'automatic_receipt_print' => false,
        ];
    }
}
