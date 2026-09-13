<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Enums;

/** Operational readiness independent of booking occupancy. */
enum UnitOperationalStatus: string
{
    case Ready = 'ready';
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    /** Get the operator-facing label. */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** Determine whether a concrete unit may be allocated in this state. */
    public function isAllocatable(): bool
    {
        return $this === self::Ready;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Ready => [self::Dirty, self::Maintenance],
            self::Dirty => [self::Cleaning, self::Maintenance],
            self::Cleaning => [self::Ready, self::Maintenance],
            self::Maintenance => [self::Ready, self::OutOfService],
            self::OutOfService => [self::Maintenance],
        };
    }

    /** Determine whether this readiness state may move directly to a target. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
