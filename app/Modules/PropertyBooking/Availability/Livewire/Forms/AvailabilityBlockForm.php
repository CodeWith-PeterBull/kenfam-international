<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Livewire\Forms;

use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates a property-local concrete-unit availability block request. */
final class AvailabilityBlockForm extends Form
{
    public string $propertyId = '';

    public string $unitId = '';

    public string $type = 'maintenance';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public string $internalNote = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'propertyId' => ['required', 'integer', Rule::exists('property_booking_properties', 'id')],
            'unitId' => ['required', 'integer', Rule::exists('property_booking_units', 'id')],
            'type' => ['required', Rule::enum(AvailabilityBlockType::class)],
            'startsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'endsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:startsAt'],
            'reason' => ['required', 'string', 'max:255'],
            'internalNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'propertyId' => 'property', 'unitId' => 'concrete unit',
            'startsAt' => 'block start', 'endsAt' => 'block end',
            'internalNote' => 'internal note',
        ];
    }

    /** Reset to a short future maintenance interval in property-local time. */
    public function resetForProperty(?Property $property = null): void
    {
        $this->reset();
        $this->propertyId = $property === null ? '' : (string) $property->getKey();
        $this->type = AvailabilityBlockType::Maintenance->value;
        $timezone = $property?->timezone ?? (string) config('property-booking.defaults.timezone', 'Africa/Nairobi');
        $startsAt = CarbonImmutable::now($timezone)->addHour()->startOfHour();
        $this->startsAt = $startsAt->format('Y-m-d\TH:i');
        $this->endsAt = $startsAt->addHours(2)->format('Y-m-d\TH:i');
        $this->resetValidation();
    }

    /** Convert property-local block-start input to the canonical UTC instant. */
    public function startsAtUtc(Property $property): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->startsAt, $property->timezone)->utc();
    }

    /** Convert property-local block-end input to the canonical UTC instant. */
    public function endsAtUtc(Property $property): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->endsAt, $property->timezone)->utc();
    }
}
