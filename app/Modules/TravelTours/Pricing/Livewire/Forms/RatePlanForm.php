<?php

/** Validates rate plan policy and its participant fares as one operator submission. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Pricing\Data\ParticipantRateData;
use App\Modules\TravelTours\Pricing\Data\RatePlanData;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Carbon\CarbonImmutable;
use Livewire\Form;

/** Convert operator strings into typed plan and fare input without floats. */
final class RatePlanForm extends Form
{
    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $currency = 'KES';

    public bool $taxInclusive = true;

    public string $taxRatePercent = '0';

    public string $depositType = 'percentage';

    public string $depositValue = '30';

    public string $balanceDueDays = '45';

    public bool $isRefundable = true;

    public bool $isActive = true;

    public bool $isPublic = true;

    public bool $isDefault = false;

    public string $minimumParticipants = '1';

    public string $maximumParticipants = '';

    public string $displayOrder = '0';

    /** @var list<array{type: string, amount: string, minimum_age: string, maximum_age: string, active_from: string, active_until: string, is_active: bool}> */
    public array $rates = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
            'taxInclusive' => ['boolean'],
            'taxRatePercent' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'depositType' => ['required', 'in:percentage,fixed'],
            'depositValue' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'balanceDueDays' => ['required', 'integer', 'min:0', 'max:730'],
            'isRefundable' => ['boolean'],
            'isActive' => ['boolean'],
            'isPublic' => ['boolean'],
            'isDefault' => ['boolean'],
            'minimumParticipants' => ['required', 'integer', 'min:1', 'max:1000'],
            'maximumParticipants' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'displayOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'rates' => ['required', 'array', 'min:1'],
        ];
        foreach (array_keys($this->rates) as $index) {
            $rules["rates.{$index}.type"] = ['required', 'in:adult,child,infant'];
            $rules["rates.{$index}.amount"] = ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
            $rules["rates.{$index}.minimum_age"] = ['nullable', 'integer', 'min:0', 'max:120'];
            $rules["rates.{$index}.maximum_age"] = ['nullable', 'integer', 'min:0', 'max:120'];
            $rules["rates.{$index}.active_from"] = ['nullable', 'date'];
            $rules["rates.{$index}.active_until"] = ['nullable', 'date'];
            $rules["rates.{$index}.is_active"] = ['boolean'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        $messages = [
            'code.regex' => 'Codes use letters, numbers, and hyphens.',
            'taxRatePercent.regex' => 'Enter the tax rate as a percentage, for example 16 or 7.5.',
            'depositValue.regex' => 'Enter the deposit as a plain number.',
            'rates.required' => 'Add at least one fare.',
            'rates.min' => 'Add at least one fare.',
        ];
        foreach (array_keys($this->rates) as $index) {
            $messages["rates.{$index}.amount.required"] = 'Enter the fare amount.';
            $messages["rates.{$index}.amount.regex"] = 'Enter the fare as a plain decimal, for example 125000.00.';
        }

        return $messages;
    }

    /** Reset the form for a new plan with one adult fare row. */
    public function start(string $currency): void
    {
        $this->reset();
        $this->currency = strtoupper($currency);
        $this->rates = [$this->blankRate(ParticipantType::Adult)];
    }

    /** Hydrate the form from a plan and its fares. */
    public function fillFromPlan(TourRatePlan $plan): void
    {
        $this->reset();
        $this->code = $plan->code;
        $this->name = $plan->name;
        $this->description = (string) $plan->description;
        $this->currency = $plan->currency;
        $this->taxInclusive = (bool) $plan->tax_inclusive;
        $this->taxRatePercent = ScaledDecimal::formatUnsigned((int) $plan->tax_rate_basis_points, 2);
        $this->depositType = $plan->deposit_type;
        $this->depositValue = ScaledDecimal::formatUnsigned((int) $plan->deposit_value, 2);
        $this->balanceDueDays = (string) $plan->balance_due_days;
        $this->isRefundable = (bool) $plan->is_refundable;
        $this->isActive = (bool) $plan->is_active;
        $this->isPublic = (bool) $plan->is_public;
        $this->isDefault = (bool) $plan->is_default;
        $this->minimumParticipants = (string) $plan->minimum_participants;
        $this->maximumParticipants = $plan->maximum_participants === null ? '' : (string) $plan->maximum_participants;
        $this->displayOrder = (string) $plan->display_order;
        $this->rates = $plan->participantRates
            ->sortBy(fn (ParticipantRate $rate): string => $rate->participant_type->value.($rate->active_from?->toDateString() ?? '0000'))
            ->map(fn (ParticipantRate $rate): array => [
                'type' => $rate->participant_type->value,
                'amount' => ScaledDecimal::formatUnsigned((int) $rate->amount_minor, 2),
                'minimum_age' => $rate->minimum_age === null ? '' : (string) $rate->minimum_age,
                'maximum_age' => $rate->maximum_age === null ? '' : (string) $rate->maximum_age,
                'active_from' => $rate->active_from?->toDateString() ?? '',
                'active_until' => $rate->active_until?->toDateString() ?? '',
                'is_active' => (bool) $rate->is_active,
            ])->values()->all();
        if ($this->rates === []) {
            $this->rates = [$this->blankRate(ParticipantType::Adult)];
        }
    }

    /** Append an empty fare row of the given type. */
    public function addRate(string $type): void
    {
        $this->rates[] = $this->blankRate(ParticipantType::tryFrom($type) ?? ParticipantType::Adult);
    }

    /** Remove one fare row. */
    public function removeRate(int $index): void
    {
        unset($this->rates[$index]);
        $this->rates = array_values($this->rates);
    }

    /** Return the typed plan policy; percentages entered by the operator become basis points. */
    public function toData(): RatePlanData
    {
        return new RatePlanData(
            code: trim($this->code),
            name: trim($this->name),
            currency: strtoupper(trim($this->currency)),
            taxInclusive: $this->taxInclusive,
            taxRateBasisPoints: ScaledDecimal::toMinor($this->taxRatePercent, 2),
            depositType: $this->depositType,
            depositValue: ScaledDecimal::toMinor($this->depositValue, 2),
            balanceDueDays: (int) $this->balanceDueDays,
            isRefundable: $this->isRefundable,
            isActive: $this->isActive,
            isPublic: $this->isPublic,
            isDefault: $this->isDefault,
            minimumParticipants: (int) $this->minimumParticipants,
            maximumParticipants: $this->maximumParticipants === '' ? null : (int) $this->maximumParticipants,
            displayOrder: (int) $this->displayOrder,
            description: trim($this->description) === '' ? null : trim($this->description),
        );
    }

    /**
     * Return the typed fare set.
     *
     * @return list<ParticipantRateData>
     */
    public function toRates(): array
    {
        return array_map(fn (array $rate): ParticipantRateData => new ParticipantRateData(
            type: ParticipantType::from($rate['type']),
            amountMinor: ScaledDecimal::toMinor($rate['amount'], 2),
            minimumAge: $rate['minimum_age'] === '' ? null : (int) $rate['minimum_age'],
            maximumAge: $rate['maximum_age'] === '' ? null : (int) $rate['maximum_age'],
            activeFrom: $rate['active_from'] === '' ? null : CarbonImmutable::parse($rate['active_from']),
            activeUntil: $rate['active_until'] === '' ? null : CarbonImmutable::parse($rate['active_until']),
            isActive: (bool) $rate['is_active'],
        ), $this->rates);
    }

    /** @return array{type: string, amount: string, minimum_age: string, maximum_age: string, active_from: string, active_until: string, is_active: bool} */
    private function blankRate(ParticipantType $type): array
    {
        return ['type' => $type->value, 'amount' => '', 'minimum_age' => '', 'maximum_age' => '', 'active_from' => '', 'active_until' => '', 'is_active' => true];
    }
}
