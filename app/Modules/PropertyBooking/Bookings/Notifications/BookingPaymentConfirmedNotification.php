<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;
use App\Modules\PropertyBooking\Support\MoneyFormatter;

/** Acknowledges one completed booking payment without exposing provider metadata. */
final class BookingPaymentConfirmedNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_payment_confirmed';

    public readonly string $paymentUlid;

    /** Create a scalar-only payment acknowledgement. */
    public function __construct(string $bookingUlid, string $paymentUlid)
    {
        $this->paymentUlid = $paymentUlid;
        parent::__construct($bookingUlid);
    }

    /** Require a completed payment owned by the current booking. */
    protected function bookingMatches(Booking $booking): bool
    {
        $payment = $this->payment();

        return $payment?->booking_id === $booking->getKey()
            && $payment->status === BookingPaymentRecordStatus::Completed;
    }

    /** Build the payment acknowledgement subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} payment confirmed for {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        $payment = $this->paymentOrFail();

        return [
            'We confirmed your '.MoneyFormatter::format((int) $payment->amount_minor, $payment->currency)
                ." payment for booking {$booking->booking_number}.",
            'Payment method: '.$payment->method->label().'.',
            $this->settlementLine($booking),
        ];
    }

    /** @return array{booking_ulid: string, payment_ulid: string} */
    public function toArray(object $notifiable): array
    {
        return ['booking_ulid' => $this->bookingUlid, 'payment_ulid' => $this->paymentUlid];
    }

    /** Resolve the current payment without retaining a model in the queue payload. */
    private function payment(): ?BookingPayment
    {
        return BookingPayment::query()->where('ulid', $this->paymentUlid)->first();
    }

    /** Resolve the current payment or fail the queue job for retry. */
    private function paymentOrFail(): BookingPayment
    {
        return BookingPayment::query()->where('ulid', $this->paymentUlid)->firstOrFail();
    }
}
