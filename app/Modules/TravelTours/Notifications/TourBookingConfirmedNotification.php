<?php

/** Customer notice that the travel desk has confirmed their places. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Dedicated customer confirmation that the booking itself is confirmed. */
final class TourBookingConfirmedNotification extends QueuedTravelNotification
{
    /** Carry the confirmed booking and apply the module queue policy. */
    public function __construct(public readonly TourBooking $booking)
    {
        $this->applyQueuePolicy();
    }

    /** Build the privacy-safe mail representation for the intended recipient. */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->fresh();
        $money = static fn (int $minor): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
        $timezone = $booking->departure_timezone_snapshot;
        $outstanding = max($booking->total_minor - ($booking->paid_minor - $booking->refunded_minor), 0);

        return (new MailMessage)
            ->subject("Booking {$booking->booking_number} confirmed")
            ->greeting("Hello {$booking->customer_name_snapshot},")
            ->line("Your places on {$booking->tour_name_snapshot} are confirmed.")
            ->line('Travel dates: '.$booking->departure_starts_at_snapshot->timezone($timezone)->format('d M Y').' to '.$booking->departure_ends_at_snapshot->timezone($timezone)->format('d M Y').'.')
            ->line($outstanding > 0 ? "Outstanding balance: {$money($outstanding)}; our travel desk will remind you before it is due." : 'Your booking is paid in full.')
            ->action('View booking', app(BookingAccessUrlService::class)->tracking($booking))
            ->line('Your booking summary is available from the link above.');
    }
}
