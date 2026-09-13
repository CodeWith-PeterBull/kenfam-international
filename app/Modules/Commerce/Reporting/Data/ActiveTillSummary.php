<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use Carbon\CarbonImmutable;

/**
 * Current open-till projection rendered by the Commerce dashboard.
 */
final readonly class ActiveTillSummary
{
    public function __construct(
        public string $ulid,
        public string $registerName,
        public string $registerCode,
        public string $cashierName,
        public CarbonImmutable $openedAt,
        public int $expectedCashMinor,
    ) {}
}
