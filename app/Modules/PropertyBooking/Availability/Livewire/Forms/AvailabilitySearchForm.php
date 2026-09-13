<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates a property-local advisory availability and pricing request. */
final class AvailabilitySearchForm extends Form
{
    public string $propertyId = '';

    public string $unitTypeId = '';

    public string $ratePlanId = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'propertyId' => ['required', 'integer', Rule::exists('property_booking_properties', 'id')],
            'unitTypeId' => ['required', 'integer', Rule::exists('property_booking_unit_types', 'id')],
            'ratePlanId' => ['required', 'integer', Rule::exists('property_booking_rate_plans', 'id')],
            'startsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'endsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:startsAt'],
            'adults' => ['required', 'integer', 'min:1', 'max:65535'],
            'children' => ['required', 'integer', 'min:0', 'max:65535'],
            'infants' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'propertyId' => 'property', 'unitTypeId' => 'unit type',
            'ratePlanId' => 'rate plan', 'startsAt' => 'arrival', 'endsAt' => 'departure',
        ];
    }

    /** Reset to a useful future nightly interval in the property's timezone. */
    public function resetForProperty(?Property $property = null): void
    {
        $this->reset();
        $this->propertyId = $property === null ? '' : (string) $property->getKey();
        $timezone = $property?->timezone ?? (string) config('property-booking.defaults.timezone', 'Africa/Nairobi');
        $arrival = CarbonImmutable::now($timezone)->addDay()->setTime(14, 0);
        $this->startsAt = $arrival->format('Y-m-d\TH:i');
        $this->endsAt = $arrival->addDay()->setTime(11, 0)->format('Y-m-d\TH:i');
        $this->adults = 1;
        $this->resetValidation();
    }

    /** Convert property-local arrival input to the canonical UTC instant. */
    public function startsAtUtc(Property $property): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->startsAt, $property->timezone)->utc();
    }

    /** Convert property-local departure input to the canonical UTC instant. */
    public function endsAtUtc(Property $property): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->endsAt, $property->timezone)->utc();
    }
}
