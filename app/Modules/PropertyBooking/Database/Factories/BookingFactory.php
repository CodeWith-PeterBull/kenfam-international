<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
final class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->addDays(7)->setTime(14, 0);

        return [
            'booking_number' => null,
            'channel' => BookingChannel::Web,
            'property_id' => Property::factory(),
            'primary_guest_id' => Guest::factory(),
            'register_id' => null,
            'reception_shift_id' => null,
            'receptionist_id' => null,
            'created_by' => null,
            'updated_by' => null,
            'status' => BookingStatus::Held,
            'stay_status' => StayStatus::Expected,
            'payment_status' => BookingPaymentStatus::Unpaid,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
            'property_timezone' => fn (array $attributes): string => Property::query()->findOrFail($attributes['property_id'])->timezone,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'currency' => fn (array $attributes): string => Property::query()->findOrFail($attributes['property_id'])->currency,
            'accommodation_subtotal_minor' => 100_000,
            'charges_subtotal_minor' => 0,
            'discount_minor' => 0,
            'discount_reason' => null,
            'tax_minor' => 0,
            'total_minor' => 100_000,
            'required_deposit_minor' => 50_000,
            'paid_minor' => 0,
            'tax_inclusive' => true,
            'property_name' => fn (array $attributes): string => Property::query()->findOrFail($attributes['property_id'])->name,
            'property_code' => fn (array $attributes): string => Property::query()->findOrFail($attributes['property_id'])->code,
            'property_address_summary' => fn (array $attributes): ?string => Property::query()->findOrFail($attributes['property_id'])->address_line_1,
            'guest_first_name' => fn (array $attributes): string => Guest::query()->findOrFail($attributes['primary_guest_id'])->first_name,
            'guest_middle_name' => fn (array $attributes): ?string => Guest::query()->findOrFail($attributes['primary_guest_id'])->middle_name,
            'guest_last_name' => fn (array $attributes): string => Guest::query()->findOrFail($attributes['primary_guest_id'])->last_name,
            'guest_email' => fn (array $attributes): ?string => Guest::query()->findOrFail($attributes['primary_guest_id'])->email,
            'guest_phone' => fn (array $attributes): ?string => Guest::query()->findOrFail($attributes['primary_guest_id'])->phone,
            'guest_country_code' => fn (array $attributes): ?string => Guest::query()->findOrFail($attributes['primary_guest_id'])->country_code,
            'special_requests' => null,
            'internal_note' => null,
            'external_reference' => null,
            'cancellation_reason' => null,
            'no_show_reason' => null,
            'hold_expires_at' => now()->addMinutes((int) config('property-booking.booking.hold_minutes', 15)),
            'pending_expires_at' => null,
            'placed_at' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'no_show_at' => null,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'completed_at' => null,
            'hold_expiring_notified_at' => null,
            'arrival_due_notified_at' => null,
            'departure_due_notified_at' => null,
        ];
    }

    /** Configure the factory for the confirmed state. */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'booking_number' => 'BKG-'.now()->format('Ymd').'-'.fake()->unique()->numerify('########'),
            'status' => BookingStatus::Confirmed,
            'hold_expires_at' => null,
            'placed_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    /** Mark the booking as placed but awaiting confirmation or payment. */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'booking_number' => 'WEB-'.now()->format('Ymd').'-'.fake()->unique()->numerify('########'),
            'status' => BookingStatus::Pending,
            'hold_expires_at' => null,
            'pending_expires_at' => now()->addMinutes((int) config('property-booking.booking.pending_minutes', 60)),
            'placed_at' => now(),
        ]);
    }

    /** Mark a confirmed booking as currently checked in. */
    public function checkedIn(): static
    {
        return $this->confirmed()->state(fn (): array => [
            'stay_status' => StayStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    /** Mark the reservation and stay lifecycle complete. */
    public function completed(): static
    {
        return $this->confirmed()->state(fn (): array => [
            'status' => BookingStatus::Completed,
            'stay_status' => StayStatus::CheckedOut,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /** Attach the factory graph to the requested property. */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (): array => [
            'property_id' => $property->id,
            'property_timezone' => $property->timezone,
            'currency' => $property->currency,
            'property_name' => $property->name,
            'property_code' => $property->code,
            'property_address_summary' => $property->address_line_1,
        ]);
    }

    /** Attach the factory graph to the requested guest. */
    public function forGuest(Guest $guest): static
    {
        return $this->state(fn (): array => [
            'primary_guest_id' => $guest->id,
            'guest_first_name' => $guest->first_name,
            'guest_middle_name' => $guest->middle_name,
            'guest_last_name' => $guest->last_name,
            'guest_email' => $guest->email,
            'guest_phone' => $guest->phone,
            'guest_country_code' => $guest->country_code,
        ]);
    }
}
