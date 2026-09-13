<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Services;

use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Produces advisory available-unit results; placement must allocate again under locks. */
final class AvailabilitySearchService
{
    /** @return Collection<int, AccommodationUnit> */
    public function availableUnits(UnitType $unitType, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $limit = null): Collection
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return collect();
        }

        $unitType->loadMissing('property');
        $buffer = max(0, (int) $unitType->property->turnover_minutes);
        $conflictStartsBefore = $endsAt->addMinutes($buffer);
        $conflictEndsAfter = $startsAt->subMinutes($buffer);
        $limit ??= max(1, (int) config('property-booking.booking.availability_candidate_limit', 100));

        return AccommodationUnit::query()
            ->allocatable()
            ->where('property_id', $unitType->property_id)
            ->where('unit_type_id', $unitType->getKey())
            ->whereDoesntHave('assignments', static function (Builder $query) use ($conflictStartsBefore, $conflictEndsAfter): void {
                $query->where('status', UnitAssignmentStatus::Active->value)
                    ->where('starts_at', '<', $conflictStartsBefore)
                    ->where('ends_at', '>', $conflictEndsAfter);
            })
            ->whereDoesntHave('availabilityBlocks', static function (Builder $query) use ($conflictStartsBefore, $conflictEndsAfter): void {
                $query->where('status', AvailabilityBlockStatus::Active->value)
                    ->where('starts_at', '<', $conflictStartsBefore)
                    ->where('ends_at', '>', $conflictEndsAfter);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** Count allocatable concrete units for the requested interval. */
    public function availableCount(UnitType $unitType, CarbonImmutable $startsAt, CarbonImmutable $endsAt): int
    {
        return $this->availableUnits($unitType, $startsAt, $endsAt)->count();
    }
}
