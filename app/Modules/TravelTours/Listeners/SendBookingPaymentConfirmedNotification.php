<?php

/**
 * Handles one TravelTours event through an isolated side effect.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\Notifications\BookingPaymentConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Routes one payment notice to the retained booking email snapshot. */
final class SendBookingPaymentConfirmedNotification implements ShouldQueue
{
    /** Handle the committed event through its isolated notification side effect. */
    public function handle(BookingPaymentConfirmed $event): void
    {
        $payment = $event->payment->loadMissing('booking');
        $email = trim((string) $payment->booking->customer_email_snapshot);
        if ($email !== '') {
            Notification::route('mail', $email)->notify(new BookingPaymentConfirmedNotification($payment));
        }
    }
}
