<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingStaffNotification;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;

/** Alerts readiness operators when checked-out units require turnover. */
final class UnitTurnoverRequiredNotification extends QueuedBookingStaffNotification
{
    protected const EVENT_KEY = 'unit_turnover_required';

    /** Require accommodation-readiness capability. */
    protected function permission(): string
    {
        return PropertyBookingPermission::MANAGE_READINESS;
    }

    /** Require the completed departure state. */
    protected function bookingMatches(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Completed && $booking->stay_status === StayStatus::CheckedOut;
    }

    /** Build the housekeeping turnover subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} unit turnover required: {$booking->booking_number}";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        $units = $booking->unitAssignments
            ->map(static fn ($assignment): ?string => $assignment->unit?->display_name ?: $assignment->unit?->code)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return [
            "Checkout for {$booking->booking_number} at {$booking->property_name} is complete.",
            'Units requiring readiness review: '.($units !== '' ? $units : 'See booking assignment history').'.',
            'Move each unit through the documented dirty, cleaning, and ready workflow.',
        ];
    }

    /** Load released assignment history and concrete-unit labels. */
    protected function relations(): array
    {
        return ['unitAssignments.unit'];
    }
}
