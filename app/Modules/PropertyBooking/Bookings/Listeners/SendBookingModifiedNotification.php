<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingModified;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingModifiedNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes a safe booking-change summary to the guest snapshot. */
final readonly class SendBookingModifiedNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the revised booking summary for its guest. */
    public function handle(BookingModified $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_modified',
            bookingUlid: $event->bookingUlid,
            notification: new BookingModifiedNotification($event->bookingUlid, $event->changeType),
        );
    }
}
