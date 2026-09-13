<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;

/** Informs a guest that an overdue arrival was recorded as a no-show. */
final class BookingNoShowNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_no_show';

    /** Require matching reservation and stay no-show states. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::NoShow && $booking->stay_status === StayStatus::NoShow;
    }

    /** Build the reviewed no-show subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} booking {$booking->booking_number} no-show update";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Booking {$booking->booking_number} at {$booking->property_name} was marked as a no-show.",
            $this->stayLine($booking),
            'Please contact the property team promptly if this status is incorrect.',
        ];
    }
}
