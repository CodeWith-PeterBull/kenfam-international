<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Data;

use App\Modules\PropertyBooking\Operations\Enums\OperationsDashboardRange;
use Carbon\CarbonImmutable;

/** Immutable, property-scoped read model for accommodation administration. */
final readonly class PropertyBookingDashboardSnapshot
{
    /**
     * @param  array<string, int>  $readinessCounts
     * @param  list<BookingTrendPoint>  $bookingTrend
     * @param  list<BookingActionSummary>  $actionQueue
     * @param  list<RecentBookingSummary>  $recentBookings
     * @param  list<ActiveReceptionShiftSummary>  $activeShifts
     */
    public function __construct(
        public OperationsDashboardRange $range,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $currencyCode,
        public int $bookedValueMinor,
        public int $paymentsCollectedMinor,
        public int $bookingsReceived,
        public int $averageBookingValueMinor,
        public int $mixedCurrencyBookingsExcluded,
        public int $arrivalsToday,
        public int $departuresToday,
        public int $inHouseStays,
        public int $activeBookings,
        public int $occupiedUnits,
        public int $activeUnits,
        public int $openShiftCount,
        public array $readinessCounts,
        public array $bookingTrend,
        public array $actionQueue,
        public array $recentBookings,
        public array $activeShifts,
    ) {}

    /** Format the bounded UTC financial period. */
    public function periodLabel(): string
    {
        return $this->startsAt->format('d M Y').' - '.$this->endsAt->format('d M Y').' UTC';
    }

    /** Calculate occupancy against active concrete units. */
    public function occupancyPercentage(): int
    {
        return $this->activeUnits > 0
            ? (int) round(($this->occupiedUnits / $this->activeUnits) * 100)
            : 0;
    }

    /** Sum every operational action queue count. */
    public function actionCount(): int
    {
        return array_sum(array_map(
            static fn (BookingActionSummary $summary): int => $summary->count,
            $this->actionQueue,
        ));
    }
}
