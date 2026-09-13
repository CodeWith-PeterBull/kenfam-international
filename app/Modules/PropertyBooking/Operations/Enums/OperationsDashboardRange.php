<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Enums;

use Carbon\CarbonImmutable;

/** Supported inclusive UTC reporting periods for accommodation operations. */
enum OperationsDashboardRange: int
{
    case SevenDays = 7;
    case ThirtyDays = 30;
    case NinetyDays = 90;

    public static function default(): self
    {
        return self::ThirtyDays;
    }

    /** @return list<int> */
    public static function values(): array
    {
        return array_map(static fn (self $range): int => $range->value, self::cases());
    }

    /** Get the operator-facing range label. */
    public function label(): string
    {
        return "{$this->value} days";
    }

    /** Resolve the inclusive UTC range boundary. */
    public function startsAt(CarbonImmutable $now): CarbonImmutable
    {
        return $now->startOfDay()->subDays($this->value - 1);
    }
}
