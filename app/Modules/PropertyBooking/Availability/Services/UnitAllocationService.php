<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Contracts\AllocatesUnits;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/** Commits exact-unit allocations with deterministic locks and overlap checks. */
final readonly class UnitAllocationService implements AllocatesUnits
{
    /** Create the unit allocation service with its required dependencies. */
    public function __construct(private DatabaseManager $database, private RecordsSystemActivity $activities) {}

    /** Allocate exact concrete units to the requested stay. */
    public function allocate(BookingStay $stay, ?User $actor = null, ?int $preferredUnitId = null): UnitAssignment
    {
        return $this->database->transaction(function () use ($stay, $actor, $preferredUnitId): UnitAssignment {
            $stay = BookingStay::query()->lockForUpdate()->findOrFail($stay->getKey());
            $stay->loadMissing(['booking.property', 'unitType']);
            if (! $stay->booking->isAvailabilityConsuming()) {
                throw new AvailabilityException('Only an availability-consuming booking can receive a unit.');
            }
            if ($stay->unitType === null || $stay->unitType->property_id !== $stay->booking->property_id) {
                throw new AvailabilityException('The booking stay does not have a valid property unit type.');
            }
            if ($stay->ends_at->lessThanOrEqualTo($stay->starts_at)) {
                throw new AvailabilityException('The assignment interval must end after it starts.');
            }

            $existing = UnitAssignment::query()->active()->where('booking_stay_id', $stay->getKey())->lockForUpdate()->first();
            if ($existing instanceof UnitAssignment) {
                return $existing->load('unit');
            }

            UnitType::query()->whereKey($stay->unit_type_id)->lockForUpdate()->firstOrFail();
            $candidates = $this->candidateUnits($stay, $preferredUnitId);
            foreach ($candidates as $unit) {
                if ($this->hasConflict($unit, CarbonImmutable::instance($stay->starts_at), CarbonImmutable::instance($stay->ends_at), $stay->booking->property->turnover_minutes)) {
                    continue;
                }

                $assignment = new UnitAssignment;
                $assignment->forceFill([
                    'property_id' => $stay->booking->property_id,
                    'booking_id' => $stay->booking_id,
                    'booking_stay_id' => $stay->getKey(),
                    'unit_id' => $unit->getKey(),
                    'status' => UnitAssignmentStatus::Active,
                    'starts_at' => $stay->starts_at,
                    'ends_at' => $stay->ends_at,
                    'active_stay_guard' => $stay->getKey(),
                    'assigned_by' => $actor?->getKey(),
                    'assigned_at' => now(),
                    'released_by' => null,
                    'released_at' => null,
                    'release_reason' => null,
                ])->save();

                $this->activities->record(
                    activityType: 'property-booking.unit-assignment.created',
                    description: "Unit {$unit->code} allocated to a booking stay",
                    actor: $actor,
                    subject: $assignment,
                    properties: ['booking_ulid' => $stay->booking->ulid, 'unit_ulid' => $unit->ulid],
                    severity: SystemActivitySeverity::Notice,
                    source: 'property-booking-availability',
                );

                return $assignment->refresh()->load('unit');
            }

            throw new AvailabilityException('No concrete unit remains available for the requested stay interval.');
        });
    }

    /** Release the active domain record transactionally. */
    public function release(UnitAssignment $assignment, string $reason, ?User $actor = null): UnitAssignment
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 255) {
            throw new AvailabilityException('An allocation release requires a reason of at most 255 characters.');
        }

        return $this->database->transaction(function () use ($assignment, $reason, $actor): UnitAssignment {
            $assignment = UnitAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            if ($assignment->status === UnitAssignmentStatus::Released) {
                return $assignment;
            }

            $assignment->forceFill([
                'status' => UnitAssignmentStatus::Released,
                'active_stay_guard' => null,
                'released_by' => $actor?->getKey(),
                'released_at' => now(),
                'release_reason' => $reason,
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.unit-assignment.released',
                description: 'Booking unit allocation released',
                actor: $actor,
                subject: $assignment,
                properties: ['booking_id' => $assignment->booking_id, 'reason' => $reason],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-availability',
            );

            return $assignment->refresh();
        });
    }

    /** @return Collection<int, AccommodationUnit> */
    private function candidateUnits(BookingStay $stay, ?int $preferredUnitId): Collection
    {
        $query = AccommodationUnit::query()
            ->allocatable()
            ->where('property_id', $stay->booking->property_id)
            ->where('unit_type_id', $stay->unit_type_id);

        if ($preferredUnitId !== null) {
            $query->whereKey($preferredUnitId);
        }

        return $query
            ->orderBy('id')
            ->limit(max(1, (int) config('property-booking.booking.availability_candidate_limit', 100)))
            ->lockForUpdate()
            ->get();
    }

    /** Determine whether a unit conflicts with the requested interval. */
    private function hasConflict(AccommodationUnit $unit, CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $turnoverMinutes): bool
    {
        $buffer = max(0, $turnoverMinutes);
        $startsBefore = $endsAt->addMinutes($buffer);
        $endsAfter = $startsAt->subMinutes($buffer);

        $assigned = UnitAssignment::query()
            ->where('unit_id', $unit->getKey())
            ->where('status', UnitAssignmentStatus::Active->value)
            ->where('starts_at', '<', $startsBefore)
            ->where('ends_at', '>', $endsAfter)
            ->lockForUpdate()
            ->exists();
        if ($assigned) {
            return true;
        }

        return AvailabilityBlock::query()
            ->where('unit_id', $unit->getKey())
            ->where('status', AvailabilityBlockStatus::Active->value)
            ->where('starts_at', '<', $startsBefore)
            ->where('ends_at', '>', $endsAfter)
            ->lockForUpdate()
            ->exists();
    }
}
