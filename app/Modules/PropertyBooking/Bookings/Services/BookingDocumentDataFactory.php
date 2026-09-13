<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Modules\PropertyBooking\Bookings\Data\Documents\BookingDocumentData;
use App\Modules\PropertyBooking\Bookings\Data\Documents\BookingDocumentStayData;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use Carbon\CarbonImmutable;

/** Projects booking snapshots into the narrow guest-document boundary. */
final class BookingDocumentDataFactory
{
    /** Build an immutable document without operational or protected fields. */
    public function fromBooking(Booking $booking): BookingDocumentData
    {
        $booking->loadMissing('stays');
        $guestName = collect([$booking->guest_first_name, $booking->guest_middle_name, $booking->guest_last_name])
            ->map(fn ($value): string => $this->text($value, ''))
            ->filter()
            ->implode(' ');

        return new BookingDocumentData(
            bookingNumber: (string) $booking->booking_number,
            statusLabel: $booking->status->label(),
            stayStatusLabel: $booking->stay_status->label(),
            paymentStatusLabel: $booking->payment_status->label(),
            paymentPreferenceLabel: $booking->preferred_payment_method?->label() ?? 'To be confirmed',
            propertyName: $this->text($booking->property_name, 'Accommodation'),
            propertyAddress: $this->nullable($booking->property_address_summary),
            guestName: $guestName !== '' ? $guestName : 'Guest',
            guestEmail: $this->nullable($booking->guest_email),
            guestPhone: $this->nullable($booking->guest_phone),
            startsAt: CarbonImmutable::instance($booking->starts_at)->timezone($booking->property_timezone),
            endsAt: CarbonImmutable::instance($booking->ends_at)->timezone($booking->property_timezone),
            timezone: $booking->property_timezone,
            currency: $booking->currency,
            adults: $booking->adult_count,
            children: $booking->child_count,
            infants: $booking->infant_count,
            accommodationSubtotalMinor: $booking->accommodation_subtotal_minor,
            taxMinor: $booking->tax_minor,
            totalMinor: $booking->total_minor,
            requiredDepositMinor: $booking->required_deposit_minor,
            paidMinor: $booking->paid_minor,
            balanceMinor: $booking->balance_minor,
            taxInclusive: $booking->tax_inclusive,
            specialRequests: $this->nullable($booking->special_requests),
            placedAt: $booking->placed_at === null ? null : CarbonImmutable::instance($booking->placed_at)->timezone($booking->property_timezone),
            stays: $booking->stays->map(fn (BookingStay $stay): BookingDocumentStayData => new BookingDocumentStayData(
                unitTypeName: $this->text($stay->unit_type_name, 'Accommodation'),
                ratePlanName: $this->text($stay->rate_plan_name, 'Standard rate'),
                pricingUnitLabel: $stay->pricing_unit->label(),
                billableUnits: $stay->billable_units,
                adults: $stay->adult_count,
                children: $stay->child_count,
                infants: $stay->infant_count,
                unitRateMinor: $stay->unit_rate_minor,
                taxMinor: $stay->tax_minor,
                totalMinor: $stay->total_minor,
            ))->values()->all(),
        );
    }

    /** Collapse controls before any snapshot text reaches HTML or PDF output. */
    private function text(mixed $value, string $fallback): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $value));
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }

    /** Return sanitized optional text. */
    private function nullable(mixed $value): ?string
    {
        $value = $this->text($value, '');

        return $value !== '' ? $value : null;
    }
}
