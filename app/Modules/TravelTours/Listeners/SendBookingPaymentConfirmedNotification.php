<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\Notifications\BookingPaymentConfirmedNotification;
use Illuminate\Support\Facades\Notification;

/** Routes one payment notice to the retained booking email snapshot; the notification itself is queued. */
final class SendBookingPaymentConfirmedNotification
{
    /** Hand the committed event to one queued notification when delivery is enabled. */
    public function handle(BookingPaymentConfirmed $event): void
    {
        $payment = $event->payment->loadMissing('booking');
        $email = trim((string) $payment->booking->customer_email_snapshot);
        if (! config('travel-tours.notifications.enabled', true) || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify(new BookingPaymentConfirmedNotification($payment));
    }
}
