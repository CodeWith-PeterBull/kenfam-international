<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Listeners;

use App\Modules\PropertyBooking\Notifications\OperationalNotificationDispatcher;
use App\Modules\PropertyBooking\PointOfBooking\Events\ReceptionShiftVarianceDetected;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Notifications\ReceptionShiftVarianceNotification;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Routes material reception variance alerts to scoped shift managers. */
final readonly class SendReceptionShiftVarianceNotification
{
    /** Create the listener with the failure-isolating dispatcher. */
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    /** Queue one material reconciliation alert for scoped shift managers. */
    public function handle(ReceptionShiftVarianceDetected $event): void
    {
        $propertyId = $event->propertyId > 0
            ? $event->propertyId
            : (int) ReceptionShift::query()->where('ulid', $event->shiftUlid)->value('property_id');
        if ($propertyId <= 0) {
            return;
        }

        $this->notifications->toStaff(
            eventKey: 'reception_shift_variance',
            propertyId: $propertyId,
            permission: PropertyBookingPermission::MANAGE_SHIFTS,
            subjectUlid: $event->shiftUlid,
            notification: new ReceptionShiftVarianceNotification(
                $event->shiftUlid,
                $propertyId,
                $event->varianceMinor,
                $event->thresholdMinor,
            ),
        );
    }
}
