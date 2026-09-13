<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingConfirmationNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes a committed web booking to one customer acknowledgement. */
final readonly class SendBookingConfirmationNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the customer acknowledgement for a committed web booking. */
    public function handle(WebBookingPlaced $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'web_booking_customer',
            bookingUlid: $event->bookingUlid,
            notification: new BookingConfirmationNotification($event->bookingUlid),
        );
    }
}
