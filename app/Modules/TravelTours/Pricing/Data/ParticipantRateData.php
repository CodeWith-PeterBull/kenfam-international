<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use Carbon\CarbonImmutable;

/** One participant fare with its optional age band and date window. */
final readonly class ParticipantRateData
{
    /** Carry a fare row; overlap and ordering rules are enforced by the service. */
    public function __construct(
        public ParticipantType $type,
        public int $amountMinor,
        public ?int $minimumAge = null,
        public ?int $maximumAge = null,
        public ?CarbonImmutable $activeFrom = null,
        public ?CarbonImmutable $activeUntil = null,
        public bool $isActive = true,
    ) {}
}
