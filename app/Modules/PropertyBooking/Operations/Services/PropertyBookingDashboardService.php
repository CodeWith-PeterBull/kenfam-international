<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Services;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Operations\Data\ActiveReceptionShiftSummary;
use App\Modules\PropertyBooking\Operations\Data\BookingActionSummary;
use App\Modules\PropertyBooking\Operations\Data\BookingTrendPoint;
use App\Modules\PropertyBooking\Operations\Data\PropertyBookingDashboardSnapshot;
use App\Modules\PropertyBooking\Operations\Data\RecentBookingSummary;
use App\Modules\PropertyBooking\Operations\Enums\OperationsDashboardRange;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Builds bounded, read-only accommodation aggregates inside operator property scope. */
final readonly class PropertyBookingDashboardService
{
    /** Create the read service with canonical property scoping. */
    public function __construct(private PropertyAccessService $access) {}

    /** Build one complete dashboard snapshot using explicit UTC financial boundaries. */
    public function snapshot(
        User $actor,
        OperationsDashboardRange $range,
        ?CarbonImmutable $now = null,
    ): PropertyBookingDashboardSnapshot {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $startsAt = $range->startsAt($now);
        $endsAt = $now->endOfDay();
        $currency = strtoupper((string) config('property-booking.defaults.currency', 'KES'));
        $periodBookings = $this->periodBookings($actor, $startsAt, $endsAt);
        $contributing = (clone $periodBookings)->whereIn('status', $this->contributingStatuses());
        $currencyBookings = (clone $contributing)->where('currency', $currency);
        $currencyBookingCount = (clone $currencyBookings)->count();
        $bookedValue = (int) (clone $currencyBookings)->sum('total_minor');
        $payments = $this->completedPayments($actor, $startsAt, $endsAt, $currency);
        $readiness = $this->readinessCounts($actor);
        $activeUnits = $this->scopedUnitQuery($actor)->where('is_active', true)->count();
        $occupiedUnits = $this->scopedUnitQuery($actor)
            ->where('is_active', true)
            ->whereHas('assignments', static fn (Builder $assignment): Builder => $assignment
                ->where('status', UnitAssignmentStatus::Active->value)
                ->whereHas('booking', static fn (Builder $booking): Builder => $booking
                    ->where('stay_status', StayStatus::CheckedIn->value)))
            ->count();
        $actionQueue = $this->actionQueue($actor, $now);

        return new PropertyBookingDashboardSnapshot(
            range: $range,
            startsAt: $startsAt,
            endsAt: $endsAt,
            currencyCode: $currency,
            bookedValueMinor: $bookedValue,
            paymentsCollectedMinor: (int) (clone $payments)->sum('amount_minor'),
            bookingsReceived: (clone $periodBookings)->count(),
            averageBookingValueMinor: $currencyBookingCount > 0 ? intdiv($bookedValue, $currencyBookingCount) : 0,
            mixedCurrencyBookingsExcluded: (clone $contributing)->where('currency', '!=', $currency)->count(),
            arrivalsToday: $this->localCalendarCount($actor, $now, 'starts_at', false),
            departuresToday: $this->localCalendarCount($actor, $now, 'ends_at', true),
            inHouseStays: $this->scopedBookingQuery($actor)->where('stay_status', StayStatus::CheckedIn->value)->count(),
            activeBookings: $this->scopedBookingQuery($actor)->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ])->count(),
            occupiedUnits: $occupiedUnits,
            activeUnits: $activeUnits,
            openShiftCount: $this->scopedShiftQuery($actor)->open()->count(),
            readinessCounts: $readiness,
            bookingTrend: $this->bookingTrend($actor, $range, $startsAt, $endsAt, $currency),
            actionQueue: $actionQueue,
            recentBookings: $this->recentBookings($actor),
            activeShifts: $this->activeShifts($actor),
        );
    }

    /** Return numbered bookings placed inside the inclusive UTC reporting period. */
    private function periodBookings(User $actor, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Builder
    {
        return $this->scopedBookingQuery($actor)
            ->whereNotNull('booking_number')
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$startsAt, $endsAt])
            ->where('status', '!=', BookingStatus::Held->value);
    }

    /** Return completed, in-currency payments collected in the reporting period. */
    private function completedPayments(
        User $actor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $currency,
    ): Builder {
        $propertyIds = $this->accessiblePropertyIds($actor);

        return BookingPayment::query()
            ->where('status', BookingPaymentRecordStatus::Completed->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startsAt, $endsAt])
            ->whereHas('booking', static fn (Builder $booking): Builder => $booking
                ->whereIn('property_id', $propertyIds)
                ->where('currency', $currency));
    }

    /** @return list<string> */
    private function contributingStatuses(): array
    {
        return [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Completed->value,
        ];
    }

    /** Count arrivals or departures using each property's own civil day. */
    private function localCalendarCount(
        User $actor,
        CarbonImmutable $now,
        string $column,
        bool $departures,
    ): int {
        $properties = $this->accessibleProperties($actor);
        if ($properties->isEmpty()) {
            return 0;
        }

        return $this->scopedBookingQuery($actor)
            ->when(
                $departures,
                static fn (Builder $query): Builder => $query->where('stay_status', StayStatus::CheckedIn->value),
                static fn (Builder $query): Builder => $query
                    ->where('status', BookingStatus::Confirmed->value)
                    ->where('stay_status', StayStatus::Expected->value),
            )
            ->where(function (Builder $windows) use ($properties, $now, $column): void {
                foreach ($properties as $property) {
                    $local = $now->timezone($property->timezone);
                    $windows->orWhere(static fn (Builder $window): Builder => $window
                        ->where('property_id', $property->id)
                        ->whereBetween($column, [$local->startOfDay()->utc(), $local->endOfDay()->utc()]));
                }
            })
            ->count();
    }

    /** @return array<string, int> */
    private function readinessCounts(User $actor): array
    {
        $rows = $this->scopedUnitQuery($actor)
            ->where('is_active', true)
            ->selectRaw('operational_status, COUNT(*) as aggregate')
            ->groupBy('operational_status')
            ->pluck('aggregate', 'operational_status');

        return collect(UnitOperationalStatus::cases())
            ->mapWithKeys(static fn (UnitOperationalStatus $status): array => [
                $status->value => (int) ($rows[$status->value] ?? 0),
            ])
            ->all();
    }

    /** @return list<BookingTrendPoint> */
    private function bookingTrend(
        User $actor,
        OperationsDashboardRange $range,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $currency,
    ): array {
        $rows = $this->periodBookings($actor, $startsAt, $endsAt)
            ->whereIn('status', $this->contributingStatuses())
            ->where('currency', $currency)
            ->whereIn('channel', array_map(static fn (BookingChannel $channel): string => $channel->value, BookingChannel::cases()))
            ->selectRaw('DATE(placed_at) as booking_date, channel, SUM(total_minor) as booked_minor')
            ->groupByRaw('DATE(placed_at), channel')
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $channel = $row->channel instanceof BookingChannel ? $row->channel->value : (string) $row->channel;
            $indexed[(string) $row->booking_date][$channel] = (int) $row->booked_minor;
        }

        $series = [];
        for ($day = $startsAt->startOfDay(); $day->lte($endsAt); $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = new BookingTrendPoint(
                date: $date,
                label: $range === OperationsDashboardRange::NinetyDays ? $day->format('d M') : $day->format('D d'),
                webMinor: $indexed[$date][BookingChannel::Web->value] ?? 0,
                pointOfBookingMinor: $indexed[$date][BookingChannel::PointOfBooking->value] ?? 0,
                adminMinor: $indexed[$date][BookingChannel::Admin->value] ?? 0,
            );
        }

        return $series;
    }

    /** @return list<BookingActionSummary> */
    private function actionQueue(User $actor, CarbonImmutable $now): array
    {
        return [
            new BookingActionSummary(
                key: 'confirmation',
                label: 'Pending confirmation',
                description: 'Placed bookings awaiting review',
                count: $this->scopedBookingQuery($actor)->where('status', BookingStatus::Pending->value)->count(),
                icon: 'ti-calendar-question',
                tone: 'warning',
                filter: ['booking-status' => BookingStatus::Pending->value],
            ),
            new BookingActionSummary(
                key: 'arrival',
                label: 'Arrival review',
                description: 'Expected arrivals now due',
                count: $this->scopedBookingQuery($actor)
                    ->where('status', BookingStatus::Confirmed->value)
                    ->where('stay_status', StayStatus::Expected->value)
                    ->where('starts_at', '<=', $now)
                    ->count(),
                icon: 'ti-door-enter',
                tone: 'info',
                filter: ['booking-status' => BookingStatus::Confirmed->value, 'booking-stay' => StayStatus::Expected->value],
            ),
            new BookingActionSummary(
                key: 'balance',
                label: 'Outstanding balance',
                description: 'Active bookings not fully settled',
                count: $this->scopedBookingQuery($actor)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->whereColumn('paid_minor', '<', 'total_minor')
                    ->count(),
                icon: 'ti-credit-card-pay',
                tone: 'danger',
                filter: ['booking-payment' => 'unpaid'],
            ),
            new BookingActionSummary(
                key: 'departure',
                label: 'Departure review',
                description: 'In-house stays now due out',
                count: $this->scopedBookingQuery($actor)
                    ->where('stay_status', StayStatus::CheckedIn->value)
                    ->where('ends_at', '<=', $now)
                    ->count(),
                icon: 'ti-door-exit',
                tone: 'warning',
                filter: ['booking-stay' => StayStatus::CheckedIn->value],
            ),
        ];
    }

    /** @return list<RecentBookingSummary> */
    private function recentBookings(User $actor): array
    {
        return $this->scopedBookingQuery($actor)
            ->whereNotNull('booking_number')
            ->whereNotNull('placed_at')
            ->latest('placed_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(static fn (Booking $booking): RecentBookingSummary => new RecentBookingSummary(
                ulid: (string) $booking->ulid,
                bookingNumber: (string) $booking->booking_number,
                propertyName: $booking->property_name,
                guestName: trim($booking->guest_first_name.' '.$booking->guest_last_name),
                channel: $booking->channel,
                status: $booking->status,
                stayStatus: $booking->stay_status,
                paymentStatus: $booking->payment_status,
                totalMinor: (int) $booking->total_minor,
                currency: $booking->currency,
                propertyTimezone: $booking->property_timezone,
                startsAt: $booking->starts_at->toImmutable(),
                placedAt: $booking->placed_at->toImmutable(),
            ))
            ->all();
    }

    /** @return list<ActiveReceptionShiftSummary> */
    private function activeShifts(User $actor): array
    {
        return $this->scopedShiftQuery($actor)
            ->where('status', ReceptionShiftStatus::Open->value)
            ->whereNull('closed_at')
            ->with(['property:id,name,currency', 'register:id,name,code', 'receptionist.profile'])
            ->latest('opened_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(static fn (ReceptionShift $shift): ActiveReceptionShiftSummary => new ActiveReceptionShiftSummary(
                ulid: (string) $shift->ulid,
                propertyName: $shift->property->name,
                registerName: $shift->register->name,
                registerCode: $shift->register->code,
                receptionistName: $shift->receptionist->display_name,
                currency: $shift->property->currency,
                openedAt: $shift->opened_at->toImmutable(),
                expectedCashMinor: (int) $shift->expected_cash_minor,
            ))
            ->all();
    }

    /** Build a booking query restricted to accessible properties. */
    private function scopedBookingQuery(User $actor): Builder
    {
        return $this->access->scope(Booking::query(), $actor, 'property_bookings.property_id');
    }

    /** Build a concrete-unit query restricted to accessible properties. */
    private function scopedUnitQuery(User $actor): Builder
    {
        return $this->access->scope(AccommodationUnit::query(), $actor, 'property_booking_units.property_id');
    }

    /** Build a reception-shift query restricted to accessible properties. */
    private function scopedShiftQuery(User $actor): Builder
    {
        return $this->access->scope(ReceptionShift::query(), $actor, 'property_booking_shifts.property_id');
    }

    /** @return Collection<int, Property> */
    private function accessibleProperties(User $actor): Collection
    {
        return $this->access
            ->scope(Property::query(), $actor, 'property_booking_properties.id')
            ->get(['id', 'timezone']);
    }

    /** @return Collection<int, int> */
    private function accessiblePropertyIds(User $actor): Collection
    {
        return $this->access->assignedPropertyIds($actor);
    }
}
