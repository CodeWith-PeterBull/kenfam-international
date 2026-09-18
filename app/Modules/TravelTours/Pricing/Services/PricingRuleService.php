<?php

/**
 * Owns seasonal, group, and early-bird pricing rule writes.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Services;

use App\Modules\TravelTours\Pricing\Data\PricingRuleData;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Database\DatabaseManager;

/**
 * Rules never disappear: booking price lines reference the rule that produced
 * them, so rules are deactivated rather than deleted. Every window is kept in
 * order and a departure-specific rule must belong to the plan's tour.
 */
final readonly class PricingRuleService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Create a rule under a plan. */
    public function create(TourRatePlan $plan, PricingRuleData $data): PricingRule
    {
        return $this->database->transaction(function () use ($plan, $data): PricingRule {
            $plan = TourRatePlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $rule = new PricingRule;
            $rule->forceFill(['rate_plan_id' => $plan->getKey()] + $this->payload($plan, $data));
            $rule->save();

            return $rule->refresh();
        });
    }

    /** Update a rule in place. */
    public function update(PricingRule $rule, PricingRuleData $data): PricingRule
    {
        return $this->database->transaction(function () use ($rule, $data): PricingRule {
            $rule = PricingRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            $plan = TourRatePlan::query()->lockForUpdate()->findOrFail($rule->rate_plan_id);
            $rule->forceFill($this->payload($plan, $data));
            $rule->save();

            return $rule->refresh();
        });
    }

    /** Switch a rule on or off without touching its definition. */
    public function setActive(PricingRule $rule, bool $active): PricingRule
    {
        return $this->database->transaction(function () use ($rule, $active): PricingRule {
            $rule = PricingRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            if ($rule->is_active !== $active) {
                $rule->forceFill(['is_active' => $active])->save();
            }

            return $rule;
        });
    }

    /** Normalize and validate rule input against its plan. */
    private function payload(TourRatePlan $plan, PricingRuleData $data): array
    {
        $name = trim($data->name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidRateConfiguration('A rule needs a name of up to 160 characters.');
        }
        if ($data->adjustmentType === AdjustmentType::Percentage && ($data->adjustmentValue < -10000 || $data->adjustmentValue > 10000)) {
            throw new InvalidRateConfiguration('Percentage adjustments are expressed in basis points between -10,000 and 10,000.');
        }
        if ($data->adjustmentType === AdjustmentType::Override && $data->adjustmentValue < 0) {
            throw new InvalidRateConfiguration('An override fare cannot be negative.');
        }
        if ($data->travelStartsOn !== null && $data->travelEndsOn !== null && $data->travelEndsOn->lt($data->travelStartsOn)) {
            throw new InvalidRateConfiguration('The travel window must end on or after it starts.');
        }
        if ($data->salesStartAt !== null && $data->salesEndAt !== null && $data->salesEndAt->lt($data->salesStartAt)) {
            throw new InvalidRateConfiguration('The sales window must end after it starts.');
        }
        if (($data->minimumParticipants !== null && $data->minimumParticipants < 1) || ($data->minimumAdvanceDays !== null && $data->minimumAdvanceDays < 0) || $data->priority < 0) {
            throw new InvalidRateConfiguration('Minimum participants must be at least one; advance days and priority cannot be negative.');
        }
        if ($data->departureId !== null && ! TourDeparture::query()->whereKey($data->departureId)->where('tour_id', $plan->tour_id)->exists()) {
            throw new InvalidRateConfiguration('The selected departure does not belong to this tour.');
        }

        return [
            'departure_id' => $data->departureId,
            'name' => $name,
            'rule_type' => $data->ruleType,
            'travel_starts_on' => $data->travelStartsOn?->toDateString(),
            'travel_ends_on' => $data->travelEndsOn?->toDateString(),
            'sales_start_at' => $data->salesStartAt,
            'sales_end_at' => $data->salesEndAt,
            'minimum_participants' => $data->minimumParticipants,
            'minimum_advance_days' => $data->minimumAdvanceDays,
            'adjustment_type' => $data->adjustmentType,
            'adjustment_value' => $data->adjustmentValue,
            'priority' => $data->priority,
            'is_stackable' => $data->isStackable,
            'is_active' => $data->isActive,
        ];
    }
}
