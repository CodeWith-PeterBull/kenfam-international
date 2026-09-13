<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Notifications\NewWebBookingReceivedNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Routes a new web-booking alert to scoped booking managers. */
final readonly class SendNewWebBookingReceivedNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue the new-booking alert for scoped managers. */
    public function handle(WebBookingPlaced $event): void
    {
        $propertyId = $event->propertyId > 0
            ? $event->propertyId
            : (int) Booking::query()->where('ulid', $event->bookingUlid)->value('property_id');
        if ($propertyId <= 0) {
            return;
        }

        $this->notifications->toStaff(
            eventKey: 'web_booking_staff',
            propertyId: $propertyId,
            permission: PropertyBookingPermission::MANAGE_BOOKINGS,
            subjectUlid: $event->bookingUlid,
            notification: new NewWebBookingReceivedNotification($event->bookingUlid, $propertyId),
        );
    }
}
