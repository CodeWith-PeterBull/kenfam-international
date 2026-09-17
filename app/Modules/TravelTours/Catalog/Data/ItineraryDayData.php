<?php

/** Carries one validated, tour-owned itinerary day. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Data;

/** Immutable day narrative and optional operational context. */
final readonly class ItineraryDayData
{
    /** @param list<string> $meals */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public array $meals = [],
        public ?string $accommodation = null,
        public ?int $startDestinationId = null,
        public ?int $endDestinationId = null,
    ) {}
}
