<?php

/** Validates exact-money input for one tour's default public rate plan. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Pricing\Data\TourBasePriceData;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Support\ScaledDecimal;
use Livewire\Form;

/** Convert decimal strings to integer minor units without floats. */
final class TourBasePriceForm extends Form
{
    public string $name = 'Standard rate';

    public string $currency = 'KES';

    public string $adult = '';

    public string $child = '';

    public string $infant = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
            'adult' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'child' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'infant' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ];
    }

    /** Hydrate the default plan without altering advanced policy fields. */
    public function fillFromPlan(?TourRatePlan $plan): void
    {
        $this->currency = strtoupper((string) ($plan?->currency ?? config('travel-tours.defaults.currency', 'KES')));
        if (! $plan) {
            return;
        }
        $this->name = $plan->name;
        foreach (ParticipantType::cases() as $type) {
            $rate = $plan->participantRates->first(fn ($item): bool => $item->participant_type === $type && $item->is_active && $item->active_from === null && $item->active_until === null);
            $this->{$type->value} = $rate ? ScaledDecimal::formatUnsigned($rate->amount_minor, 2) : '';
        }
    }

    /** Return typed, exact-money input for the pricing service. */
    public function toData(): TourBasePriceData
    {
        return new TourBasePriceData(trim($this->name), strtoupper(trim($this->currency)),
            $this->minor($this->adult) ?? 0, $this->minor($this->child), $this->minor($this->infant));
    }

    /** Parse a validated decimal string into a minor-unit integer. */
    private function minor(string $amount): ?int
    {
        if ($amount === '') {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
