<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Data\Documents;

use Carbon\CarbonImmutable;

/** Stable guest-visible booking projection shared by PDF orientations. */
final readonly class BookingDocumentData
{
    /** @param list<BookingDocumentStayData> $stays */
    public function __construct(
        public string $bookingNumber,
        public string $statusLabel,
        public string $stayStatusLabel,
        public string $paymentStatusLabel,
        public string $paymentPreferenceLabel,
        public string $propertyName,
        public ?string $propertyAddress,
        public string $guestName,
        public ?string $guestEmail,
        public ?string $guestPhone,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $timezone,
        public string $currency,
        public int $adults,
        public int $children,
        public int $infants,
        public int $accommodationSubtotalMinor,
        public int $taxMinor,
        public int $totalMinor,
        public int $requiredDepositMinor,
        public int $paidMinor,
        public int $balanceMinor,
        public bool $taxInclusive,
        public ?string $specialRequests,
        public ?CarbonImmutable $placedAt,
        public array $stays,
    ) {}

    /** Return the number of reserved accommodation lines. */
    public function stayCount(): int
    {
        return count($this->stays);
    }
}
