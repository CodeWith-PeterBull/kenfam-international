<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

/** Validated commercial policy for one tour rate plan; participant fares travel separately. */
final readonly class RatePlanData
{
    /** Carry normalized plan attributes; the service enforces uniqueness and usage guards. */
    public function __construct(
        public string $code,
        public string $name,
        public string $currency,
        public bool $taxInclusive,
        public int $taxRateBasisPoints,
        public string $depositType,
        public int $depositValue,
        public int $balanceDueDays,
        public bool $isRefundable,
        public bool $isActive,
        public bool $isPublic,
        public bool $isDefault,
        public int $minimumParticipants,
        public ?int $maximumParticipants,
        public int $displayOrder = 0,
        public ?string $description = null,
    ) {}
}
