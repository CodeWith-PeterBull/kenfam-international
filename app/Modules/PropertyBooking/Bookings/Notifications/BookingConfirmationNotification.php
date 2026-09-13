<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;
use App\Modules\PropertyBooking\Support\MoneyFormatter;

/** Acknowledges receipt of one committed self-service booking. */
final class BookingConfirmationNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'web_booking_customer';

    /** Keep delivery limited to a live web booking. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->channel === BookingChannel::Web
            && in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    /** Build the booking-received subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} booking {$booking->booking_number} received";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        return [
            "We received booking {$booking->booking_number} for {$booking->property_name}.",
            $this->stayLine($booking),
            'Total: '.MoneyFormatter::format((int) $booking->total_minor, $booking->currency)
                .'. Payment preference: '.($booking->preferred_payment_method?->label() ?? 'To be confirmed').'.',
            'The property team will confirm the booking after completing its operational review.',
        ];
    }

    /** Label the signed booking tracker action. */
    protected function actionLabel(): string
    {
        return 'Track your booking';
    }
}
