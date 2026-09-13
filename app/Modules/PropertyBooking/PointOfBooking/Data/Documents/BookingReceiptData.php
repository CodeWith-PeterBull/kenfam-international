<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Data\Documents;

use Carbon\CarbonImmutable;

/** Canonical immutable input for browser, thermal, and PDF booking receipts. */
final readonly class BookingReceiptData
{
    /** @param list<BookingReceiptStayData> $stays @param list<BookingReceiptPaymentData> $payments */
    public function __construct(
        public string $bookingNumber,
        public CarbonImmutable $placedAt,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $propertyTimezone,
        public string $propertyName,
        public ?string $propertyAddress,
        public string $registerName,
        public string $registerCode,
        public string $receptionistName,
        public string $guestName,
        public ?string $guestContact,
        public string $currency,
        public int $accommodationSubtotalMinor,
        public int $chargesSubtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public int $totalMinor,
        public int $paidMinor,
        public bool $taxInclusive,
        public array $stays,
        public array $payments,
    ) {}

    /** Count all rendered stay lines. */
    public function stayCount(): int
    {
        return count($this->stays);
    }

    /** Count all completed rendered tenders. */
    public function tenderCount(): int
    {
        return count($this->payments);
    }
}
