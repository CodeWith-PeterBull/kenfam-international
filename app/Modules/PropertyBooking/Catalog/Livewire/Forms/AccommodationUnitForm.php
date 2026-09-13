<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates concrete room, apartment, house, or space administration input. */
final class AccommodationUnitForm extends Form
{
    public ?AccommodationUnit $unit = null;

    public string $propertyId = '';

    public string $unitTypeId = '';

    public string $code = '';

    public string $displayName = '';

    public string $floorLabel = '';

    public string $locationNote = '';

    public string $internalNote = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $propertyId = (int) $this->propertyId;

        return [
            'propertyId' => ['required', 'integer', Rule::exists('property_booking_properties', 'id')],
            'unitTypeId' => ['required', 'integer', Rule::exists('property_booking_unit_types', 'id')],
            'code' => [
                'required', 'string', 'max:60',
                Rule::unique('property_booking_units', 'code')
                    ->where(static fn (Builder $query): Builder => $query->where('property_id', $propertyId))
                    ->ignore($this->unit),
            ],
            'displayName' => ['nullable', 'string', 'max:140'],
            'floorLabel' => ['nullable', 'string', 'max:80'],
            'locationNote' => ['nullable', 'string', 'max:180'],
            'internalNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'propertyId' => 'property', 'unitTypeId' => 'unit type',
            'displayName' => 'display name', 'floorLabel' => 'floor or wing',
            'locationNote' => 'location note', 'internalNote' => 'internal note',
        ];
    }

    /** Populate every editable concrete-unit field. */
    public function fillFromUnit(AccommodationUnit $unit): void
    {
        $this->unit = $unit;
        $this->propertyId = (string) $unit->property_id;
        $this->unitTypeId = (string) $unit->unit_type_id;
        $this->code = $unit->code;
        $this->displayName = (string) $unit->display_name;
        $this->floorLabel = (string) $unit->floor_label;
        $this->locationNote = (string) $unit->location_note;
        $this->internalNote = (string) $unit->internal_note;
    }

    /** Reset to concrete-unit creation defaults for an optional property. */
    public function resetForCreate(?int $propertyId = null): void
    {
        $this->reset();
        $this->unit = null;
        $this->propertyId = $propertyId === null ? '' : (string) $propertyId;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'code' => strtoupper(trim($this->code)),
            'display_name' => self::nullableTrimmed($this->displayName),
            'floor_label' => self::nullableTrimmed($this->floorLabel),
            'location_note' => self::nullableTrimmed($this->locationNote),
            'internal_note' => self::nullableTrimmed($this->internalNote),
        ];
    }

    /** Normalize optional text for persistence. */
    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
