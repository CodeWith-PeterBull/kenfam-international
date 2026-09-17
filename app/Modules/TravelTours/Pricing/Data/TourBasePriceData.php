<?php

/** Carries exact base fares for the public default plan of one tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

/** Immutable base pricing input; optional child/infant fares are not invented. */
final readonly class TourBasePriceData
{
    /** Keep required adult and optional child/infant amounts in minor units. */
    public function __construct(
        public string $name,
        public string $currency,
        public int $adultMinor,
        public ?int $childMinor,
        public ?int $infantMinor,
    ) {}
}
