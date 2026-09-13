<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;

/** Confirms booking cancellation to the immutable guest email snapshot. */
final class BookingCancelledNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_cancelled_customer';

    /** Require the current cancelled state. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Cancelled;
    }

    /** Build the guest cancellation subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} booking {$booking->booking_number} cancelled";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Booking {$booking->booking_number} at {$booking->property_name} has been cancelled.",
            $this->stayLine($booking),
            'Please contact the property team if this cancellation is unexpected or a settlement remains outstanding.',
        ];
    }

    /** Label the signed cancellation action. */
    protected function actionLabel(): string
    {
        return 'View cancellation';
    }
}
