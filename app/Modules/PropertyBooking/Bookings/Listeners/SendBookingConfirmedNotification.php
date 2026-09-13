<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingConfirmed;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingConfirmedNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes one accepted-booking confirmation to its guest snapshot. */
final readonly class SendBookingConfirmedNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the accepted-booking confirmation for its guest. */
    public function handle(BookingConfirmed $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_confirmed',
            bookingUlid: $event->bookingUlid,
            notification: new BookingConfirmedNotification($event->bookingUlid),
        );
    }
}
