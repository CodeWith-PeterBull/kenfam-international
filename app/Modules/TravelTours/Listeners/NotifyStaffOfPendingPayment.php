<?php

/** Tells the operators who can confirm payments that evidence is waiting. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Listeners;

use App\Modules\TravelTours\Events\BookingPaymentRecorded;
use App\Modules\TravelTours\Notifications\BookingPaymentRecordedNotification;
use App\Modules\TravelTours\Support\StaffRecipientResolver;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Support\Facades\Notification;

/** Routes one internal notice to every operator holding the confirm-payments grant. */
final class NotifyStaffOfPendingPayment
{
    /** Resolve recipients by capability so the list follows the access catalogue. */
    public function __construct(private readonly StaffRecipientResolver $recipients) {}

    /** Hand the committed event to one queued notification per recipient when delivery is enabled. */
    public function handle(BookingPaymentRecorded $event): void
    {
        if (! config('travel-tours.notifications.enabled', true)) {
            return;
        }

        $recipients = $this->recipients->holding(TravelToursPermission::CONFIRM_PAYMENTS);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new BookingPaymentRecordedNotification($event->payment));
    }
}
