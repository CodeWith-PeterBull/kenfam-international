<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptData;
use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptPaymentData;
use App\Modules\PropertyBooking\PointOfBooking\Data\Documents\BookingReceiptStayData;
use Carbon\CarbonImmutable;

/** Projects a POB aggregate into one sanitized receipt contract. */
final class BookingReceiptDataFactory
{
    /** Build the canonical receipt from historical booking snapshots. */
    public function fromBooking(Booking $booking): BookingReceiptData
    {
        $booking->loadMissing(['stays', 'payments', 'register', 'receptionist']);

        return new BookingReceiptData(
            bookingNumber: $this->text($booking->booking_number, 'Booking'),
            placedAt: CarbonImmutable::instance($booking->placed_at ?? $booking->created_at),
            startsAt: CarbonImmutable::instance($booking->starts_at),
            endsAt: CarbonImmutable::instance($booking->ends_at),
            propertyTimezone: $this->text($booking->property_timezone, 'UTC'),
            propertyName: $this->text($booking->property_name, 'Property'),
            propertyAddress: $this->nullableText($booking->property_address_summary),
            registerName: $this->text($booking->register?->name, 'Reception'),
            registerCode: $this->text($booking->register?->code, 'Not available'),
            receptionistName: $this->text($booking->receptionist?->display_name, 'Reception team'),
            guestName: $this->text(collect([$booking->guest_first_name, $booking->guest_middle_name, $booking->guest_last_name])->filter()->implode(' '), 'Guest'),
            guestContact: $this->maskedContact($booking->guest_email, $booking->guest_phone),
            currency: $booking->currency,
            accommodationSubtotalMinor: $booking->accommodation_subtotal_minor,
            chargesSubtotalMinor: $booking->charges_subtotal_minor,
            discountMinor: $booking->discount_minor,
            taxMinor: $booking->tax_minor,
            totalMinor: $booking->total_minor,
            paidMinor: $booking->paid_minor,
            taxInclusive: $booking->tax_inclusive,
            stays: $booking->stays->map(fn ($stay): BookingReceiptStayData => new BookingReceiptStayData(
                unitTypeName: $this->text($stay->unit_type_name, 'Accommodation'),
                ratePlanName: $this->text($stay->rate_plan_name, 'Rate'),
                billableUnits: $stay->billable_units,
                pricingUnitLabel: $stay->pricing_unit->label(),
                occupantCount: $stay->adult_count + $stay->child_count + $stay->infant_count,
                unitRateMinor: $stay->unit_rate_minor,
                taxMinor: $stay->tax_minor,
                totalMinor: $stay->total_minor,
            ))->values()->all(),
            payments: $booking->payments
                ->where('status', BookingPaymentRecordStatus::Completed)
                ->map(fn ($payment): BookingReceiptPaymentData => new BookingReceiptPaymentData(
                    methodLabel: $payment->method->label(),
                    reference: $this->nullableText($payment->reference),
                    amountMinor: $payment->amount_minor,
                    changeMinor: $payment->change_minor,
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

    /** Return sanitized text or an explicit fallback. */
    private function text(mixed $value, string $fallback): string
    {
        return $this->nullableText($value) ?? $fallback;
    }

    /** Remove browser/PDF control characters from stored snapshots. */
    private function nullableText(mixed $value): ?string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $value));
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
