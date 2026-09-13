<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Notifications;

use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Notifications\QueuedBookingCustomerNotification;
use App\Modules\PropertyBooking\Support\MoneyFormatter;

/** Informs a guest that safe stay or unit details changed. */
final class BookingModifiedNotification extends QueuedBookingCustomerNotification
{
    protected const EVENT_KEY = 'booking_modified';

    public readonly string $changeType;

    /** Create an update message carrying only a bounded change discriminator. */
    public function __construct(string $bookingUlid, string $changeType)
    {
        $this->changeType = $changeType;
        parent::__construct($bookingUlid);
    }

    /** Require a live booking and recognized modification type. */
    protected function bookingMatches(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
            && in_array($this->changeType, ['interval', 'unit_move'], true);
    }

    /** Build the revised-booking subject. */
    protected function subject(Booking $booking, string $institutionName): string
    {
        return "{$institutionName} booking {$booking->booking_number} updated";
    }

    /** @return list<string> */
    protected function lines(Booking $booking): array
    {
        $change = $this->changeType === 'unit_move' ? 'assigned accommodation' : 'stay dates and pricing';

        return [
            "The {$change} for booking {$booking->booking_number} has been updated.",
            $this->stayLine($booking),
            'Current total: '.MoneyFormatter::format((int) $booking->total_minor, $booking->currency).'.',
            $this->settlementLine($booking),
        ];
    }

    /** @return array{booking_ulid: string, change_type: string} */
    public function toArray(object $notifiable): array
    {
        return ['booking_ulid' => $this->bookingUlid, 'change_type' => $this->changeType];
    }
}
