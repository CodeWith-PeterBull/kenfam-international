<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;

/** Confirms completed arrival processing to the current guest. */
final class BookingCheckedInNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_checked_in';

    /** Require the current in-house state. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Confirmed && $booking->stay_status === StayStatus::CheckedIn;
    }

    /** Build the completed check-in subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} check-in complete: {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Check-in for booking {$booking->booking_number} at {$booking->property_name} is complete.",
            $this->stayLine($booking),
            $this->settlementLine($booking),
        ];
    }

    /** Label the signed in-house booking action. */
    protected function actionLabel(): string
    {
        return 'View stay details';
    }
}
