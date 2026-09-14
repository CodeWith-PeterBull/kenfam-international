<?php

/**
 * Handles one TravelTours event through an isolated side effect.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\TourBookingPlaced;
use App\Modules\TravelTours\Notifications\TourBookingPlacedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Routes booking confirmation only to the booking email snapshot. */
final class SendTourBookingPlacedNotification implements ShouldQueue
{
    /** Handle the committed event through its isolated notification side effect. */
    public function handle(TourBookingPlaced $event): void
    {
        $email = trim((string) $event->booking->customer_email_snapshot);
        if ($email !== '') {
            Notification::route('mail', $email)->notify(new TourBookingPlacedNotification($event->booking));
        }
    }
}
