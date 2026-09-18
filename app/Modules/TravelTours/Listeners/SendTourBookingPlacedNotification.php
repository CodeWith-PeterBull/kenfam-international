<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\TourBookingPlaced;
use App\Modules\TravelTours\Notifications\TourBookingPlacedNotification;
use Illuminate\Support\Facades\Notification;

/** Routes booking confirmation only to the booking email snapshot; the notification itself is queued. */
final class SendTourBookingPlacedNotification
{
    /** Hand the committed event to one queued notification when delivery is enabled. */
    public function handle(TourBookingPlaced $event): void
    {
        $email = trim((string) $event->booking->customer_email_snapshot);
        if (! config('travel-tours.notifications.enabled', true) || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify(new TourBookingPlacedNotification($event->booking));
    }
}
