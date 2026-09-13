<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPayment> */
final class BookingPaymentFactory extends Factory
{
    protected $model = BookingPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'reception_shift_id' => null,
            'recorded_by' => User::factory(),
            'method' => BookingPaymentMethod::MobileMoney,
            'status' => BookingPaymentRecordStatus::Completed,
            'currency' => fn (array $attributes): string => Booking::query()->findOrFail($attributes['booking_id'])->currency,
            'amount_minor' => 50_000,
            'tendered_minor' => null,
            'change_minor' => 0,
            'refunded_amount_minor' => 0,
            'reference' => 'DEMO-'.strtoupper(fake()->unique()->bothify('??####??')),
            'metadata' => ['source' => 'factory'],
            'paid_at' => now(),
            'failed_at' => null,
            'refunded_at' => null,
        ];
    }

    /** Create an unresolved adapter-ready payment attempt. */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => BookingPaymentRecordStatus::Pending,
            'paid_at' => null,
        ]);
    }

    /** Create an explicitly fully refunded payment history row. */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingPaymentRecordStatus::Refunded,
            'refunded_amount_minor' => (int) $attributes['amount_minor'],
            'refunded_at' => now(),
        ]);
    }
}
