<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use Illuminate\Database\DatabaseManager;

/** Assigns a stable channel-aware human business number exactly once. */
final readonly class BookingNumberService
{
    /** Create the booking number service with its required dependencies. */
    public function __construct(private DatabaseManager $database) {}

    /** Assign a stable channel-aware booking number. */
    public function assign(Booking $booking): Booking
    {
        return $this->database->transaction(function () use ($booking): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if (filled($booking->booking_number)) {
                return $booking;
            }

            $prefix = match ($booking->channel) {
                BookingChannel::Web => (string) config('property-booking.numbering.web_prefix', 'WEB'),
                BookingChannel::PointOfBooking => (string) config('property-booking.numbering.pob_prefix', 'POB'),
                BookingChannel::Admin => (string) config('property-booking.numbering.admin_prefix', 'ADM'),
            };
            $padding = max(6, min(16, (int) config('property-booking.numbering.padding', 8)));
            $booking->forceFill([
                'booking_number' => sprintf('%s-%s-%0'.$padding.'d', strtoupper($prefix), now()->format('Ymd'), $booking->getKey()),
            ])->save();

            return $booking->refresh();
        });
    }
}
