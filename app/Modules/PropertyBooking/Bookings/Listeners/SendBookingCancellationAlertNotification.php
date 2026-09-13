<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingCancelled;
use App\Modules\PropertyBooking\Bookings\Notifications\BookingCancellationAlertNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Routes one cancellation alert to scoped booking managers. */
final readonly class SendBookingCancellationAlertNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the cancellation alert for scoped booking managers. */
    public function handle(BookingCancelled $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'booking_cancelled_staff',
            propertyId: $event->propertyId,
            permission: PropertyBookingPermission::MANAGE_BOOKINGS,
            subjectUlid: $event->bookingUlid,
            notification: new BookingCancellationAlertNotification($event->bookingUlid, $event->propertyId),
        );
    }
}
