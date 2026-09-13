<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Data;

/** Bounded public availability-search input interpreted per property timezone. */
final readonly class StaySearchData
{
    /** Create normalized public search input. */
    public function __construct(
        public string $arrivalDate,
        public string $departureDate,
        public string $arrivalTime,
        public string $departureTime,
        public int $adults,
        public int $children,
        public int $infants,
        public ?string $categorySlug = null,
        public ?string $location = null,
    ) {}
}
