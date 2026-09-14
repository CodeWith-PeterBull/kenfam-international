<?php

/**
 * Builds one dedicated TravelTours operational notification.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dedicated customer confirmation for a newly placed tour booking. */
final class TourBookingPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Initialize the TourBookingPlacedNotification with its required dependencies or immutable state. */
    public function __construct(public readonly TourBooking $booking)
    {
        $this->afterCommit();
    }

    /** Return the configured delivery channels for this dedicated notification. */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Build the privacy-safe mail representation for the intended recipient. */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('departure');
        $url = app(BookingAccessUrlService::class)->tracking($booking);

        return (new MailMessage)
            ->subject("Booking {$booking->booking_number} received")
            ->greeting("Hello {$booking->customer_name_snapshot},")
            ->line("We have received your booking for {$booking->tour_name_snapshot}.")
            ->line("Current status: {$booking->status->label()}.")
            ->line("Travel begins {$booking->departure_starts_at_snapshot->timezone($booking->departure_timezone_snapshot)->format('d M Y, H:i')}.")
            ->action('View booking', $url)
            ->line('Our travel team will contact you if any further details are required.');
    }
}
