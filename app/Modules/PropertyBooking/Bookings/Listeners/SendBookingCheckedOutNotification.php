<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedOut;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCheckedOutNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes completed departure processing to the guest snapshot. */
final readonly class SendBookingCheckedOutNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the completed checkout update for its guest. */
    public function handle(BookingCheckedOut $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_checked_out',
            bookingUlid: $event->bookingUlid,
            notification: new BookingCheckedOutNotification($event->bookingUlid),
        );
    }
}
