<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

use Carbon\CarbonImmutable;

/** Complete server-verifiable input to a tour quote. */
final readonly class TourQuoteRequest
{
    /** Initialize the TourQuoteRequest with its required dependencies or immutable state. */
    public function __construct(
        public int $departureId,
        public int $ratePlanId,
        public ParticipantMix $participants,
        public ?string $promotionCode = null,
        public ?int $customerId = null,
        public ?CarbonImmutable $quotedAt = null,
    ) {}
}
