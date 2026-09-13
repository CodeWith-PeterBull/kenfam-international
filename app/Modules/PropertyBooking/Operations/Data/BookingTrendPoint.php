<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Data;

/** One zero-filled UTC day in the cross-channel booked-value trend. */
final readonly class BookingTrendPoint
{
    /** Create one channel-split trend point. */
    public function __construct(
        public string $date,
        public string $label,
        public int $webMinor,
        public int $pointOfBookingMinor,
        public int $adminMinor,
    ) {}

    /** Sum the channel values for this UTC day. */
    public function totalMinor(): int
    {
        return $this->webMinor + $this->pointOfBookingMinor + $this->adminMinor;
    }
}
