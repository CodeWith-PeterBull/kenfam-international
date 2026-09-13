<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Listeners;

use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedOut;
use App\Modules\PropertyBooking\Bookings\Notifications\UnitTurnoverRequiredNotification;
use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Routes checked-out unit turnover alerts to scoped readiness staff. */
final readonly class SendUnitTurnoverRequiredNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue unit-turnover work for scoped readiness staff. */
    public function handle(BookingCheckedOut $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'unit_turnover_required',
            propertyId: $event->propertyId,
            permission: PropertyBookingPermission::MANAGE_READINESS,
            subjectUlid: $event->bookingUlid,
            notification: new UnitTurnoverRequiredNotification($event->bookingUlid, $event->propertyId),
        );
    }
}
