<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingPaymentConfirmed;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingPaymentConfirmedNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;

/** Routes one completed-payment acknowledgement to the guest snapshot. */
final readonly class SendBookingPaymentConfirmedNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue one completed-payment acknowledgement for its guest. */
    public function handle(BookingPaymentConfirmed $event): void
    {
        $this->notifications->toBookingCustomer(
            eventKey: 'booking_payment_confirmed',
            bookingUlid: $event->bookingUlid,
            notification: new BookingPaymentConfirmedNotification($event->bookingUlid, $event->paymentUlid),
        );
    }
}
