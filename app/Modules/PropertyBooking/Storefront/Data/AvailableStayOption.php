<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Data;

use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;

/** Public-safe available unit-type and rate projection backed by a current quote. */
final readonly class AvailableStayOption
{
    /** Create one available sellable accommodation option. */
    public function __construct(
        public UnitType $unitType,
        public RatePlan $ratePlan,
        public BookingQuote $quote,
    ) {}
}
