<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedIn;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCheckedInNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes completed arrival processing to the guest snapshot. */
final readonly class SendBookingCheckedInNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the completed check-in update for its guest. */
    public function handle(BookingCheckedIn $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_checked_in',
            bookingUlid: $event->bookingUlid,
            notification: new BookingCheckedInNotification($event->bookingUlid),
        );
    }
}
