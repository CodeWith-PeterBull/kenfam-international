<?php

/**
 * Defines an injectable TravelTours application-service boundary.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;

/** Authoritative tour quote calculator boundary. */
interface CalculatesTourQuotes
{
    /** Calculate an authoritative deterministic quote from persisted pricing records. */
    public function calculate(TourQuoteRequest $request): TourQuote;
}
