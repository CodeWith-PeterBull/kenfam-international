<?php

/** Validates one seasonal, group, or early-bird rule for a rate plan. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Forms;

use App\Modules\TravelTours\Pricing\Data\PricingRuleData;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Enums\PricingRuleType;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Carbon\CarbonImmutable;
use Livewire\Form;

/** Convert operator strings into a typed rule; percentages become basis points, money becomes minor units. */
final class PricingRuleForm extends Form
{
    public string $ratePlanId = '';

    public string $name = '';

    public string $ruleType = 'seasonal';

    public string $adjustmentType = 'percentage';

    public string $adjustmentValue = '';

    public string $departureId = '';

    public string $travelStartsOn = '';

    public string $travelEndsOn = '';

    public string $salesStartAt = '';

    public string $salesEndAt = '';

    public string $minimumParticipants = '';

    public string $minimumAdvanceDays = '';

    public string $priority = '100';

    public bool $isStackable = false;

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'ratePlanId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'ruleType' => ['required', 'in:seasonal,group,early_bird'],
            'adjustmentType' => ['required', 'in:percentage,fixed,override'],
            'adjustmentValue' => ['required', 'regex:/^-?\d{1,12}(\.\d{1,2})?$/'],
            'departureId' => ['nullable', 'integer'],
            'travelStartsOn' => ['nullable', 'date'],
            'travelEndsOn' => ['nullable', 'date'],
            'salesStartAt' => ['nullable', 'date'],
            'salesEndAt' => ['nullable', 'date'],
            'minimumParticipants' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'minimumAdvanceDays' => ['nullable', 'integer', 'min:0', 'max:730'],
            'priority' => ['required', 'integer', 'min:0', 'max:10000'],
            'isStackable' => ['boolean'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'ratePlanId.required' => 'Choose the rate plan the rule belongs to.',
            'adjustmentValue.required' => 'Enter the adjustment.',
            'adjustmentValue.regex' => 'Enter a percentage such as -10 or 12.5, or an amount such as 5000.00.',
        ];
    }

    /** Reset the form for a new rule under an optional plan. */
    public function start(?int $ratePlanId): void
    {
        $this->reset();
        $this->ratePlanId = $ratePlanId === null ? '' : (string) $ratePlanId;
    }

    /** Hydrate the form from an existing rule. */
    public function fillFromRule(PricingRule $rule): void
    {
        $this->reset();
        $this->ratePlanId = (string) $rule->rate_plan_id;
        $this->name = $rule->name;
        $this->ruleType = $rule->rule_type->value;
        $this->adjustmentType = $rule->adjustment_type->value;
        $this->adjustmentValue = $this->presentAdjustment($rule->adjustment_type, (int) $rule->adjustment_value);
        $this->departureId = $rule->departure_id === null ? '' : (string) $rule->departure_id;
        $this->travelStartsOn = $rule->travel_starts_on?->toDateString() ?? '';
        $this->travelEndsOn = $rule->travel_ends_on?->toDateString() ?? '';
        $this->salesStartAt = $rule->sales_start_at?->format('Y-m-d\TH:i') ?? '';
        $this->salesEndAt = $rule->sales_end_at?->format('Y-m-d\TH:i') ?? '';
        $this->minimumParticipants = $rule->minimum_participants === null ? '' : (string) $rule->minimum_participants;
        $this->minimumAdvanceDays = $rule->minimum_advance_days === null ? '' : (string) $rule->minimum_advance_days;
        $this->priority = (string) $rule->priority;
        $this->isStackable = (bool) $rule->is_stackable;
        $this->isActive = (bool) $rule->is_active;
    }

    /** Return the typed rule. */
    public function toData(): PricingRuleData
    {
        $type = AdjustmentType::from($this->adjustmentType);
        $negative = str_starts_with(trim($this->adjustmentValue), '-');
        $magnitude = ScaledDecimal::toMinor(ltrim(trim($this->adjustmentValue), '-'), 2);

        return new PricingRuleData(
            name: trim($this->name),
            ruleType: PricingRuleType::from($this->ruleType),
            adjustmentType: $type,
            adjustmentValue: $negative ? -$magnitude : $magnitude,
            departureId: $this->departureId === '' ? null : (int) $this->departureId,
            travelStartsOn: $this->travelStartsOn === '' ? null : CarbonImmutable::parse($this->travelStartsOn),
            travelEndsOn: $this->travelEndsOn === '' ? null : CarbonImmutable::parse($this->travelEndsOn),
            salesStartAt: $this->salesStartAt === '' ? null : CarbonImmutable::parse($this->salesStartAt),
            salesEndAt: $this->salesEndAt === '' ? null : CarbonImmutable::parse($this->salesEndAt),
            minimumParticipants: $this->minimumParticipants === '' ? null : (int) $this->minimumParticipants,
            minimumAdvanceDays: $this->minimumAdvanceDays === '' ? null : (int) $this->minimumAdvanceDays,
            priority: (int) $this->priority,
            isStackable: $this->isStackable,
            isActive: $this->isActive,
        );
    }

    /** Present a stored adjustment the way the operator entered it. */
    private function presentAdjustment(AdjustmentType $type, int $value): string
    {
        $decimal = ScaledDecimal::formatUnsigned(abs($value), 2);

        return ($value < 0 ? '-' : '').$decimal;
    }
}
