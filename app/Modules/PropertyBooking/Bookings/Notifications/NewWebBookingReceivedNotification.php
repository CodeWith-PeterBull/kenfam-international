<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingStaffNotification;
use App\Modules\PropertyBooking\Support\MoneyFormatter;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Alerts booking managers about one newly committed web booking. */
final class NewWebBookingReceivedNotification extends QueuedBookingStaffNotification
{
    protected const EVENT_KEY = 'web_booking_staff';

    /** Require booking-management capability. */
    protected function permission(): string
    {
        return PropertyBookingPermission::MANAGE_BOOKINGS;
    }

    /** Keep delivery limited to a live web booking. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->channel === BookingChannel::Web
            && in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    /** Build the staff intake subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} new web booking: {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        $guest = trim(collect([
            $booking->guest_first_name,
            $booking->guest_middle_name,
            $booking->guest_last_name,
        ])->filter()->implode(' '));

        return [
            "A web booking for {$booking->property_name} is ready for review.",
            "Guest: {$guest}. Stay: ".$booking->starts_at->timezone($booking->property_timezone)->format('d M Y, H:i')
                .' to '.$booking->ends_at->timezone($booking->property_timezone)->format('d M Y, H:i').'.',
            'Total: '.MoneyFormatter::format((int) $booking->total_minor, $booking->currency)
                .'. Payment status: '.$booking->payment_status->label().'.',
        ];
    }
}
