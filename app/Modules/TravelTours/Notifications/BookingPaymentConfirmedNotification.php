<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Dedicated customer receipt notice for one confirmed payment. */
final class BookingPaymentConfirmedNotification extends QueuedTravelNotification
{
    /** Carry the confirmed payment and apply the module queue policy. */
    public function __construct(public readonly BookingPayment $payment)
    {
        $this->applyQueuePolicy();
    }

    /** Build the privacy-safe mail representation for the intended recipient. */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->payment->booking->fresh();
        $money = static fn (int $minor): string => MoneyFormatter::format($minor, $booking->currency, $booking->currency_exponent);
        $outstanding = max($booking->total_minor - ($booking->paid_minor - $booking->refunded_minor), 0);
        $reference = $this->payment->reference ? " (reference {$this->payment->reference})." : '.';

        return (new MailMessage)
            ->subject("Payment received for {$booking->booking_number}")
            ->greeting("Hello {$booking->customer_name_snapshot},")
            ->line("We have confirmed your payment of {$money($this->payment->amount_minor)} by {$this->payment->method->label()}{$reference}")
            ->line("Payment status: {$booking->payment_status->label()}. ".($outstanding > 0 ? "Outstanding balance: {$money($outstanding)}." : 'Your booking is paid in full.'))
            ->action('View booking', app(BookingAccessUrlService::class)->tracking($booking));
    }
}
