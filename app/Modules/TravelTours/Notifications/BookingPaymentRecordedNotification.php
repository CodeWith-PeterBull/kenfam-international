<?php

/** Staff notice that payment evidence is waiting for confirmation. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Internal notice to the operators who may confirm or reject recorded payments. */
final class BookingPaymentRecordedNotification extends QueuedTravelNotification
{
    /** Carry the pending payment and apply the module queue policy. */
    public function __construct(public readonly BookingPayment $payment)
    {
        $this->applyQueuePolicy();
    }

    /** Build the operator-facing mail; it links to the workspace, never to the customer's private page. */
    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing(['booking', 'receiver']);
        $booking = $payment->booking;
        $amount = MoneyFormatter::format($payment->amount_minor, $payment->currency, $booking->currency_exponent);
        $recorder = $payment->receiver?->name ?: 'An operator';
        $provider = $payment->provider ? " via {$payment->provider}" : '';

        return (new MailMessage)
            ->subject("Payment awaiting confirmation: {$booking->booking_number}")
            ->greeting('Hello,')
            ->line("{$recorder} recorded {$amount} by {$payment->method->label()} against {$booking->booking_number} ({$booking->customer_name_snapshot}).")
            ->line('Reference: '.($payment->reference ?: 'none').$provider.'.')
            ->action('Review in the bookings workspace', route('travel-tours.admin.bookings.index', ['q' => $booking->booking_number]))
            ->line('Confirm the payment once the money is verified, or reject it with a reason.');
    }
}
