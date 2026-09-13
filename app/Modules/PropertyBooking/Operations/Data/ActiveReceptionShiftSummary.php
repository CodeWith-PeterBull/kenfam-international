<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Data;

use Carbon\CarbonImmutable;

/** Current reception shift projection without tender or reconciliation secrets. */
final readonly class ActiveReceptionShiftSummary
{
    /** Create a privacy-safe active-shift projection. */
    public function __construct(
        public string $ulid,
        public string $propertyName,
        public string $registerName,
        public string $registerCode,
        public string $receptionistName,
        public string $currency,
        public CarbonImmutable $openedAt,
        public int $expectedCashMinor,
    ) {}
}
