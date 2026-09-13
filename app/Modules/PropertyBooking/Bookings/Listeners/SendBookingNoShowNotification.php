<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingMarkedNoShow;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingNoShowNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes one reviewed no-show status to the guest snapshot. */
final readonly class SendBookingNoShowNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the reviewed no-show update for its guest. */
    public function handle(BookingMarkedNoShow $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_no_show',
            bookingUlid: $event->bookingUlid,
            notification: new BookingNoShowNotification($event->bookingUlid),
        );
    }
}
