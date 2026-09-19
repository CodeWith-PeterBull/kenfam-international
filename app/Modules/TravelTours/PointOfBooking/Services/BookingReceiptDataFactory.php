<?php

/**
 * Projects one desk booking into the sanitised receipt contract.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\BookingPriceLine;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptLineData;
use App\Modules\TravelTours\PointOfBooking\Data\Documents\BookingReceiptPaymentData;
use Carbon\CarbonImmutable;

/**
 * The receipt reads the booking's immutable snapshots, never the live tour or
 * customer, so a reprint months later shows what was sold. Contact details
 * are masked because the paper leaves the desk.
 */
final class BookingReceiptDataFactory
{
    /** Build the canonical receipt from the booking's historical snapshots. */
    public function fromBooking(TourBooking $booking): BookingReceiptData
    {
        $booking->loadMissing(['priceLines', 'payments', 'register', 'agent']);
        $netPaid = max((int) $booking->paid_minor - (int) $booking->refunded_minor, 0);

        return new BookingReceiptData(
            bookingNumber: $this->text($booking->booking_number, 'Booking'),
            placedAt: CarbonImmutable::instance($booking->placed_at ?? $booking->created_at),
            startsAt: CarbonImmutable::instance($booking->departure_starts_at_snapshot),
            endsAt: CarbonImmutable::instance($booking->departure_ends_at_snapshot),
            departureTimezone: $this->text($booking->departure_timezone_snapshot, 'UTC'),
            tourName: $this->text($booking->tour_name_snapshot, 'Tour'),
            tourCode: $this->text($booking->tour_code_snapshot, 'Not available'),
            registerName: $this->text($booking->register?->name, 'Booking desk'),
            registerCode: $this->text($booking->register?->code, 'Not available'),
            operatorName: $this->text($booking->agent?->display_name, 'Booking desk team'),
            customerName: $this->text($booking->customer_name_snapshot, 'Customer'),
            customerContact: $this->maskedContact($booking->customer_email_snapshot, $booking->customer_phone_snapshot),
            travellerCount: (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count,
            currency: $booking->currency,
            currencyExponent: (int) $booking->currency_exponent,
            subtotalMinor: (int) $booking->subtotal_minor + (int) $booking->extras_total_minor,
            discountMinor: (int) $booking->discount_total_minor,
            taxMinor: (int) $booking->tax_total_minor,
            totalMinor: (int) $booking->total_minor,
            depositMinor: (int) $booking->deposit_required_minor,
            paidMinor: $netPaid,
            balanceMinor: max((int) $booking->total_minor - $netPaid, 0),
            bookingStatusLabel: $booking->status->label(),
            lines: $booking->priceLines
                ->sortBy('display_order')
                ->map(fn (BookingPriceLine $line): BookingReceiptLineData => new BookingReceiptLineData(
                    description: $this->text($line->description, 'Fare'),
                    quantity: (int) $line->quantity,
                    unitMinor: (int) $line->unit_amount_minor,
                    totalMinor: (int) $line->total_minor,
                ))->values()->all(),
            payments: $booking->payments
                ->where('status', PaymentRecordStatus::Confirmed)
                ->sortBy('id')
                ->map(fn (BookingPayment $payment): BookingReceiptPaymentData => new BookingReceiptPaymentData(
                    methodLabel: $payment->method->label(),
                    reference: $this->nullableText($payment->reference),
                    amountMinor: (int) $payment->amount_minor,
                    changeMinor: max((int) ($payment->safe_metadata['change_minor'] ?? 0), 0),
                ))->values()->all(),
        );
    }

    /** Prefer a masked email and fall back to a masked phone number. */
    private function maskedContact(?string $email, ?string $phone): ?string
    {
        $email = trim((string) $email);
        if ($email !== '' && str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }
        $phone = preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
        if ($phone !== '') {
            return str_repeat('*', max(strlen($phone) - 4, 0)).substr($phone, -4);
        }

        return null;
    }

    /** Return sanitised text or an explicit fallback. */
    private function text(mixed $value, string $fallback): string
    {
        return $this->nullableText($value) ?? $fallback;
    }

    /** Remove browser and PDF control characters from stored snapshots. */
    private function nullableText(mixed $value): ?string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $value));
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
