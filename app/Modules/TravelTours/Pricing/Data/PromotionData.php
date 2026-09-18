<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use Carbon\CarbonImmutable;

/** Validated promotion code definition with its tour scope. */
final readonly class PromotionData
{
    /**
     * @param  list<int>  $includedTourIds
     * @param  list<int>  $excludedTourIds
     */
    public function __construct(
        public string $code,
        public string $name,
        public AdjustmentType $adjustmentType,
        public int $adjustmentValue,
        public ?string $currency,
        public bool $appliesToAllTours,
        public array $includedTourIds = [],
        public array $excludedTourIds = [],
        public ?CarbonImmutable $validFrom = null,
        public ?CarbonImmutable $validUntil = null,
        public int $minimumBookingMinor = 0,
        public int $minimumParticipants = 1,
        public ?int $maximumUses = null,
        public ?int $maximumUsesPerCustomer = null,
        public bool $isActive = true,
        public ?string $description = null,
    ) {}
}
