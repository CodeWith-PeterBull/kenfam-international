<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Data;

use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use Carbon\CarbonImmutable;

/** Privacy-minimized recent booking projection for the operations dashboard. */
final readonly class RecentBookingSummary
{
    /** Create a privacy-minimized recent-booking projection. */
    public function __construct(
        public string $ulid,
        public string $bookingNumber,
        public string $propertyName,
        public string $guestName,
        public BookingChannel $channel,
        public BookingStatus $status,
        public StayStatus $stayStatus,
        public BookingPaymentStatus $paymentStatus,
        public int $totalMinor,
        public string $currency,
        public string $propertyTimezone,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $placedAt,
    ) {}
}
