<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingStaffNotification;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Alerts scoped booking managers that a booking was cancelled. */
final class BookingCancellationAlertNotification extends QueuedBookingStaffNotification
{
    protected const EVENT_KEY = 'booking_cancelled_staff';

    /** Require booking-management capability. */
    protected function permission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }

    /** Require the current cancelled state. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Cancelled;
    }

    /** Build the operational cancellation subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} cancellation: {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "Booking {$booking->booking_number} for {$booking->property_name} was cancelled.",
            'Guest: '.trim($booking->guest_first_name.' '.$booking->guest_last_name).'.',
            'Review allocation release and any outstanding settlement action.',
        ];
    }
}
