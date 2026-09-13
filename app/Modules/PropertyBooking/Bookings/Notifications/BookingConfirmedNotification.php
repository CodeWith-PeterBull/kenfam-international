<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;

/** Confirms an accepted booking to its immutable guest email snapshot. */
final class BookingConfirmedNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_confirmed';

    /** Require the accepted booking state at delivery time. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Confirmed;
    }

    /** Build the accepted-booking subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} booking {$booking->booking_number} confirmed";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Your booking at {$booking->property_name} is confirmed.",
            $this->stayLine($booking),
            $this->settlementLine($booking),
        ];
    }

    /** Label the signed confirmed-booking action. */
    protected function actionLabel(): string
    {
        return 'View confirmed booking';
    }
}
