<?php

/** Validates exact-money optional tour extras. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Data\TourExtraData;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Collect money as a decimal string and convert it without floating point. */
final class TourExtraForm extends Form
{
    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $pricingUnit = 'per_person';

    public string $amount = '0.00';

    public string $currency = 'KES';

    /** @var list<string> */
    public array $participantTypes = [];

    public int $minimumQuantity = 0;

    public string $maximumQuantity = '';

    public bool $isActive = true;

    public bool $isPublic = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'pricingUnit' => ['required', Rule::in(['per_person', 'per_booking'])],
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
            'participantTypes' => ['array', 'max:3'],
            'participantTypes.*' => ['required', Rule::enum(ParticipantType::class)],
            'minimumQuantity' => ['required', 'integer', 'between:0,1000'],
            'maximumQuantity' => ['nullable', 'integer', 'between:1,1000', 'gte:minimumQuantity'],
            'isActive' => ['boolean'], 'isPublic' => ['boolean'],
        ];
    }

    /** Load an authorized extra without losing a cent. */
    public function fillFromExtra(TourExtra $extra): void
    {
        $this->code = $extra->code;
        $this->name = $extra->name;
        $this->description = (string) $extra->description;
        $this->pricingUnit = $extra->pricing_unit;
        $this->amount = intdiv($extra->amount_minor, 100).'.'.str_pad((string) ($extra->amount_minor % 100), 2, '0', STR_PAD_LEFT);
        $this->currency = $extra->currency;
        $this->participantTypes = $extra->participant_types ?? [];
        $this->minimumQuantity = $extra->minimum_quantity;
        $this->maximumQuantity = (string) ($extra->maximum_quantity ?? '');
        $this->isActive = $extra->is_active;
        $this->isPublic = $extra->is_public;
    }

    /** Restore defaults after an extra write. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->pricingUnit = 'per_person';
        $this->currency = strtoupper((string) config('travel-tours.defaults.currency', 'KES'));
        $this->amount = '0.00';
        $this->isActive = true;
        $this->isPublic = true;
    }

    /** Convert validated decimal input to exact minor units. */
    public function toData(): TourExtraData
    {
        [$whole, $fraction] = array_pad(explode('.', $this->amount, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return new TourExtraData(
            code: $this->code, name: $this->name, pricingUnit: $this->pricingUnit,
            amountMinor: $minor, currency: $this->currency,
            description: trim($this->description) ?: null,
            participantTypes: $this->participantTypes,
            minimumQuantity: $this->minimumQuantity,
            maximumQuantity: $this->maximumQuantity === '' ? null : (int) $this->maximumQuantity,
            isActive: $this->isActive, isPublic: $this->isPublic,
        );
    }
}
