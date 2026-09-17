<?php

/** Carries one activity within an itinerary day. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Immutable local-time, location and inclusion details. */
final readonly class ItineraryActivityData
{
    /** Capture validated local-time, location and inclusion values. */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $startsAtLocal = null,
        public ?string $endsAtLocal = null,
        public ?string $locationName = null,
        public ?int $destinationId = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public bool $isIncluded = true,
        public bool $isOptional = false,
    ) {}
}
