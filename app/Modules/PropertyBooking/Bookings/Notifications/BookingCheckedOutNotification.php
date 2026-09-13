<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;

/** Confirms departure and exposes the final safe booking summary. */
final class BookingCheckedOutNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_checked_out';

    /** Require the completed departure state. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Completed && $booking->stay_status === StayStatus::CheckedOut;
    }

    /** Build the completed checkout subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} checkout complete: {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Checkout for booking {$booking->booking_number} at {$booking->property_name} is complete.",
            $this->settlementLine($booking),
            'Thank you for staying with us.',
        ];
    }

    /** Label the signed completed-booking action. */
    protected function actionLabel(): string
    {
        return 'View completed booking';
    }
}
