<?php

/** Tells shift managers that a closed drawer was materially short or over. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Listeners;

use App\Modules\TravelTours\PointOfBooking\Events\ShiftVarianceDetected;
use App\Modules\TravelTours\PointOfBooking\Notifications\ShiftVarianceNotification;
use App\Modules\TravelTours\Support\StaffRecipientResolver;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Support\Facades\Notification;

/** Routes one queued alert to every operator holding the manage-shifts grant. */
final class SendShiftVarianceNotification
{
    /** Resolve recipients by capability so the list follows the access catalogue. */
    public function __construct(private readonly StaffRecipientResolver $recipients) {}

    /** Hand the committed event to one queued notification per recipient when delivery is enabled. */
    public function handle(ShiftVarianceDetected $event): void
    {
        if (! config('travel-tours.notifications.enabled', true)) {
            return;
        }
        $recipients = $this->recipients->holding(TravelToursPermission::MANAGE_SHIFTS);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ShiftVarianceNotification($event->shiftUlid, $event->varianceMinor, $event->thresholdMinor));
    }
}
