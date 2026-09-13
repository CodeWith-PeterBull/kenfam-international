<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Enums;

use Carbon\CarbonImmutable;

/**
 * Supported inclusive periods for Commerce administration reporting.
 */
enum CommerceDashboardRange: int
{
    case SevenDays = 7;
    case ThirtyDays = 30;
    case NinetyDays = 90;

    /**
     * Return the default dashboard reporting period.
     */
    public static function default(): self
    {
        return self::ThirtyDays;
    }

    /**
     * Return values accepted from the range query parameter.
     *
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $range): int => $range->value, self::cases());
    }

    /**
     * Return the concise option label shown by the segmented control.
     */
    public function label(): string
    {
        return "{$this->value} days";
    }

    /**
     * Resolve the inclusive first calendar day for this range.
     */
    public function startsAt(CarbonImmutable $now): CarbonImmutable
    {
        return $now->startOfDay()->subDays($this->value - 1);
    }
}
