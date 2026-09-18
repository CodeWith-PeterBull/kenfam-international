<?php

/** Tells the customer their booking is confirmed. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\TourBookingConfirmed;
use App\Modules\TravelTours\Notifications\TourBookingConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/** Routes the confirmation notice to the booking email snapshot; the notification itself is queued. */
final class SendTourBookingConfirmedNotification
{
    /** Hand the committed event to one queued notification when delivery is enabled. */
    public function handle(TourBookingConfirmed $event): void
    {
        $email = trim((string) $event->booking->customer_email_snapshot);
        if (! config('travel-tours.notifications.enabled', true) || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify(new TourBookingConfirmedNotification($event->booking));
    }
}
