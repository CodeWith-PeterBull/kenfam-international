<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

/** Creates and releases auditable concrete-unit operational blocks. */
final readonly class AvailabilityBlockService
{
    /** Create the availability block service with its required dependencies. */
    public function __construct(private DatabaseManager $database, private RecordsSystemActivity $activities) {}

    /** Create an operational availability block transactionally. */
    public function create(
        AccommodationUnit $unit,
        AvailabilityBlockType $type,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $reason,
        User $actor,
        ?string $internalNote = null,
    ): AvailabilityBlock {
        $reason = trim($reason);
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new AvailabilityException('An availability block must end after it starts.');
        }
        if ($reason === '' || strlen($reason) > 255) {
            throw new AvailabilityException('An availability block requires a reason of at most 255 characters.');
        }

        return $this->database->transaction(function () use ($unit, $type, $startsAt, $endsAt, $reason, $actor, $internalNote): AvailabilityBlock {
            $unit = AccommodationUnit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $unit->loadMissing('property');
            $buffer = max(0, $unit->property->turnover_minutes);
            $startsBefore = $endsAt->addMinutes($buffer);
            $endsAfter = $startsAt->subMinutes($buffer);

            $hasAssignment = UnitAssignment::query()
                ->where('unit_id', $unit->getKey())
                ->where('status', UnitAssignmentStatus::Active->value)
                ->where('starts_at', '<', $startsBefore)
                ->where('ends_at', '>', $endsAfter)
                ->lockForUpdate()
                ->exists();
            $hasBlock = AvailabilityBlock::query()
                ->where('unit_id', $unit->getKey())
                ->active()
                ->overlapping($startsAt, $endsAt)
                ->lockForUpdate()
                ->exists();
            if ($hasAssignment || $hasBlock) {
                throw new AvailabilityException('The unit already has a conflicting booking assignment or block.');
            }

            $block = new AvailabilityBlock;
            $block->forceFill([
                'property_id' => $unit->property_id,
                'unit_id' => $unit->getKey(),
                'type' => $type,
                'status' => AvailabilityBlockStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $reason,
                'internal_note' => $this->note($internalNote),
                'created_by' => $actor->getKey(),
                'released_by' => null,
                'released_at' => null,
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.availability-block.created',
                description: "Availability blocked for unit {$unit->code}",
                actor: $actor,
                subject: $block,
                properties: ['unit_ulid' => $unit->ulid, 'type' => $type->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-availability',
            );

            return $block->refresh();
        });
    }

    /** Release the active domain record transactionally. */
    public function release(AvailabilityBlock $block, User $actor): AvailabilityBlock
    {
        return $this->database->transaction(function () use ($block, $actor): AvailabilityBlock {
            $block = AvailabilityBlock::query()->lockForUpdate()->findOrFail($block->getKey());
            if ($block->status === AvailabilityBlockStatus::Released) {
                return $block;
            }

            $block->forceFill([
                'status' => AvailabilityBlockStatus::Released,
                'released_by' => $actor->getKey(),
                'released_at' => now(),
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.availability-block.released',
                description: 'Property availability block released',
                actor: $actor,
                subject: $block,
                properties: ['unit_id' => $block->unit_id],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-availability',
            );

            return $block->refresh();
        });
    }

    /** Normalize an optional operational note. */
    private function note(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        if ($note !== null && strlen($note) > 2_000) {
            throw new AvailabilityException('Internal availability notes cannot exceed 2,000 characters.');
        }

        return $note === '' ? null : $note;
    }
}
