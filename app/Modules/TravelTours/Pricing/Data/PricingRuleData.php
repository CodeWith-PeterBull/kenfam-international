<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Enums\PricingRuleType;
use Carbon\CarbonImmutable;

/** Validated seasonal, group, or early-bird adjustment for one rate plan. */
final readonly class PricingRuleData
{
    /** Carry a rule definition; window ordering and departure ownership are enforced by the service. */
    public function __construct(
        public string $name,
        public PricingRuleType $ruleType,
        public AdjustmentType $adjustmentType,
        public int $adjustmentValue,
        public ?int $departureId = null,
        public ?CarbonImmutable $travelStartsOn = null,
        public ?CarbonImmutable $travelEndsOn = null,
        public ?CarbonImmutable $salesStartAt = null,
        public ?CarbonImmutable $salesEndAt = null,
        public ?int $minimumParticipants = null,
        public ?int $minimumAdvanceDays = null,
        public int $priority = 100,
        public bool $isStackable = false,
        public bool $isActive = true,
    ) {}
}
