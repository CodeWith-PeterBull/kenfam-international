<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Dedicated customer confirmation for a newly placed tour booking. */
final class TourBookingPlacedNotification extends QueuedTravelNotification
{
    /** Carry the placed booking and apply the module queue policy. */
    public function __construct(public readonly TourBooking $booking)
    {
        $this->applyQueuePolicy();
    }

    /** Build the privacy-safe mail representation for the intended recipient. */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->fresh();
        $money = static fn (int $minor): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
        $starts = $booking->departure_starts_at_snapshot->timezone($booking->departure_timezone_snapshot)->format('d M Y');
        $message = (new MailMessage)
            ->subject("Booking {$booking->booking_number} received")
            ->greeting("Hello {$booking->customer_name_snapshot},")
            ->line("We have received your booking for {$booking->tour_name_snapshot}, travelling from {$starts}.")
            ->line("Total: {$money($booking->total_minor)}. Preferred payment: {$booking->preferred_payment_method->label()}.");

        if ($booking->deposit_required_minor > 0 && $booking->deposit_required_minor < $booking->total_minor) {
            $message->line("A deposit of {$money($booking->deposit_required_minor)} secures your places; our travel desk will contact you with payment instructions.");
        } else {
            $message->line('Our travel desk will contact you with payment instructions.');
        }
        if ($booking->pending_expires_at !== null) {
            $message->line('Please pay by '.$booking->pending_expires_at->timezone($booking->departure_timezone_snapshot)->format('d M Y, H:i').' ('.$booking->departure_timezone_snapshot.') to keep your places.');
        }

        return $message
            ->action('View booking', app(BookingAccessUrlService::class)->tracking($booking))
            ->line('Keep this private link; it shows the latest status of your booking.');
    }
}
