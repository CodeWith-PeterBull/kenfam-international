<?php

/** Validates one promotion code definition and its tour scope. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Forms;

use App\Modules\TravelTours\Pricing\Data\PromotionData;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Carbon\CarbonImmutable;
use Livewire\Form;

/** Convert operator strings into a typed promotion; percentages become basis points, money becomes minor units. */
final class PromotionForm extends Form
{
    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $adjustmentType = 'percentage';

    public string $adjustmentValue = '';

    public string $currency = '';

    public bool $appliesToAllTours = true;

    /** @var list<int|string> */
    public array $includedTourIds = [];

    /** @var list<int|string> */
    public array $excludedTourIds = [];

    public string $validFrom = '';

    public string $validUntil = '';

    public string $minimumBooking = '0';

    public string $minimumParticipants = '1';

    public string $maximumUses = '';

    public string $maximumUsesPerCustomer = '';

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'adjustmentType' => ['required', 'in:percentage,fixed'],
            'adjustmentValue' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'currency' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
            'appliesToAllTours' => ['boolean'],
            'includedTourIds' => ['array'],
            'includedTourIds.*' => ['integer'],
            'excludedTourIds' => ['array'],
            'excludedTourIds.*' => ['integer'],
            'validFrom' => ['nullable', 'date'],
            'validUntil' => ['nullable', 'date'],
            'minimumBooking' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'minimumParticipants' => ['required', 'integer', 'min:1', 'max:1000'],
            'maximumUses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'maximumUsesPerCustomer' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'code.regex' => 'Codes use letters, numbers, and hyphens.',
            'adjustmentValue.required' => 'Enter the discount.',
            'adjustmentValue.regex' => 'Enter a percentage such as 10 or an amount such as 5000.00.',
            'minimumBooking.regex' => 'Enter the minimum booking value as a plain decimal.',
        ];
    }

    /** Reset the form for a new promotion. */
    public function start(string $currency): void
    {
        $this->reset();
        $this->currency = strtoupper($currency);
    }

    /** Hydrate the form from an existing promotion and its assignments. */
    public function fillFromPromotion(Promotion $promotion): void
    {
        $this->reset();
        $this->code = $promotion->code;
        $this->name = $promotion->name;
        $this->description = (string) $promotion->description;
        $this->adjustmentType = $promotion->adjustment_type->value;
        $this->adjustmentValue = ScaledDecimal::formatUnsigned((int) $promotion->adjustment_value, 2);
        $this->currency = (string) ($promotion->currency ?? config('travel-tours.defaults.currency', 'KES'));
        $this->appliesToAllTours = (bool) $promotion->applies_to_all_tours;
        $this->includedTourIds = $promotion->tours->where('pivot.is_exclusion', false)->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
        $this->excludedTourIds = $promotion->tours->where('pivot.is_exclusion', true)->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
        $this->validFrom = $promotion->valid_from?->format('Y-m-d\TH:i') ?? '';
        $this->validUntil = $promotion->valid_until?->format('Y-m-d\TH:i') ?? '';
        $this->minimumBooking = ScaledDecimal::formatUnsigned((int) $promotion->minimum_booking_minor, 2);
        $this->minimumParticipants = (string) $promotion->minimum_participants;
        $this->maximumUses = $promotion->maximum_uses === null ? '' : (string) $promotion->maximum_uses;
        $this->maximumUsesPerCustomer = $promotion->maximum_uses_per_customer === null ? '' : (string) $promotion->maximum_uses_per_customer;
        $this->isActive = (bool) $promotion->is_active;
    }

    /** Return the typed promotion. */
    public function toData(): PromotionData
    {
        return new PromotionData(
            code: trim($this->code),
            name: trim($this->name),
            adjustmentType: AdjustmentType::from($this->adjustmentType),
            adjustmentValue: ScaledDecimal::toMinor($this->adjustmentValue, 2),
            currency: trim($this->currency) === '' ? null : strtoupper(trim($this->currency)),
            appliesToAllTours: $this->appliesToAllTours,
            includedTourIds: array_map('intval', $this->includedTourIds),
            excludedTourIds: array_map('intval', $this->excludedTourIds),
            validFrom: $this->validFrom === '' ? null : CarbonImmutable::parse($this->validFrom),
            validUntil: $this->validUntil === '' ? null : CarbonImmutable::parse($this->validUntil),
            minimumBookingMinor: ScaledDecimal::toMinor($this->minimumBooking, 2),
            minimumParticipants: (int) $this->minimumParticipants,
            maximumUses: $this->maximumUses === '' ? null : (int) $this->maximumUses,
            maximumUsesPerCustomer: $this->maximumUsesPerCustomer === '' ? null : (int) $this->maximumUsesPerCustomer,
            isActive: $this->isActive,
            description: trim($this->description) === '' ? null : trim($this->description),
        );
    }
}
