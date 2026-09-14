<?php

/**
 * Builds one dedicated TravelTours operational notification.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dedicated customer receipt notice for one confirmed payment. */
final class BookingPaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Initialize the BookingPaymentConfirmedNotification with its required dependencies or immutable state. */
    public function __construct(public readonly BookingPayment $payment)
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
        $booking = $this->payment->booking;

        return (new MailMessage)->subject("Payment received for {$booking->booking_number}")
            ->greeting("Hello {$booking->customer_name_snapshot},")
            ->line('Your payment has been recorded successfully.')
            ->line('Amount: '.MoneyFormatter::format($this->payment->amount_minor, $this->payment->currency, $this->payment->booking->currency_exponent))
            ->line("Payment status: {$booking->fresh()->payment_status->label()}.")
            ->action('View booking', app(BookingAccessUrlService::class)->tracking($booking));
    }
}
