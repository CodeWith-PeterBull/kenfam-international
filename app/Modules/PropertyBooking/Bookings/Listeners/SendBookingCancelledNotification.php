<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingCancelled;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCancelledNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes one cancellation confirmation to the guest snapshot. */
final readonly class SendBookingCancelledNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the cancellation acknowledgement for its guest. */
    public function handle(BookingCancelled $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_cancelled_customer',
            bookingUlid: $event->bookingUlid,
            notification: new BookingCancelledNotification($event->bookingUlid),
        );
    }
}
