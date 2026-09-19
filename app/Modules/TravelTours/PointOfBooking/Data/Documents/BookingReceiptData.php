<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Data\Documents;

use Carbon\CarbonImmutable;

/** Canonical immutable input for the browser, thermal, and PDF desk receipts. */
final readonly class BookingReceiptData
{
    /**
     * @param  list<BookingReceiptLineData>  $lines
     * @param  list<BookingReceiptPaymentData>  $payments
     */
    public function __construct(
        public string $bookingNumber,
        public CarbonImmutable $placedAt,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $departureTimezone,
        public string $tourName,
        public string $tourCode,
        public string $registerName,
        public string $registerCode,
        public string $operatorName,
        public string $customerName,
        public ?string $customerContact,
        public int $travellerCount,
        public string $currency,
        public int $currencyExponent,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public int $totalMinor,
        public int $depositMinor,
        public int $paidMinor,
        public int $balanceMinor,
        public string $bookingStatusLabel,
        public array $lines,
        public array $payments,
    ) {}

    /** Count all rendered fare lines. */
    public function lineCount(): int
    {
        return count($this->lines);
    }

    /** Count all confirmed rendered tenders. */
    public function tenderCount(): int
    {
        return count($this->payments);
    }
}
