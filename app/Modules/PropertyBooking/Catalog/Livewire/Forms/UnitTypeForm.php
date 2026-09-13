<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates sellable accommodation metadata, layout, and occupancy input. */
final class UnitTypeForm extends Form
{
    public ?UnitType $unitType = null;

    public string $propertyId = '';

    public string $code = '';

    public string $slug = '';

    public string $name = '';

    public string $shortDescription = '';

    public string $description = '';

    public string $sizeSquareMetres = '';

    public int $bedroomCount = 0;

    public int $bathroomCount = 1;

    public int $livingRoomCount = 0;

    public int $bedCount = 1;

    public string $bedConfigurationJson = "[\n    {\"type\": \"queen\", \"quantity\": 1}\n]";

    public int $maximumGuests = 1;

    public int $maximumAdults = 1;

    public int $maximumChildren = 0;

    public int $maximumInfants = 0;

    public bool $allowsInfantsOnTop = true;

    public bool $isEntireUnit = true;

    public bool $smokingAllowed = false;

    public bool $isFeatured = false;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @var list<int|string> */
    public array $amenityIds = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $propertyId = (int) $this->propertyId;

        return [
            'propertyId' => ['required', 'integer', Rule::exists('property_booking_properties', 'id')],
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('property_booking_unit_types', 'code')
                    ->where(static fn (Builder $query): Builder => $query->where('property_id', $propertyId))
                    ->ignore($this->unitType),
            ],
            'slug' => [
                'nullable', 'string', 'max:180',
                Rule::unique('property_booking_unit_types', 'slug')
                    ->where(static fn (Builder $query): Builder => $query->where('property_id', $propertyId))
                    ->ignore($this->unitType),
            ],
            'name' => ['required', 'string', 'max:180'],
            'shortDescription' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string', 'max:50000'],
            'sizeSquareMetres' => ['nullable', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'bedroomCount' => ['required', 'integer', 'min:0', 'max:65535'],
            'bathroomCount' => ['required', 'integer', 'min:0', 'max:65535'],
            'livingRoomCount' => ['required', 'integer', 'min:0', 'max:65535'],
            'bedCount' => ['required', 'integer', 'min:0', 'max:65535'],
            'bedConfigurationJson' => ['nullable', 'json', 'max:10000'],
            'maximumGuests' => ['required', 'integer', 'min:1', 'max:65535'],
            'maximumAdults' => ['required', 'integer', 'min:1', 'lte:maximumGuests'],
            'maximumChildren' => ['required', 'integer', 'min:0', 'lte:maximumGuests'],
            'maximumInfants' => ['required', 'integer', 'min:0', 'max:65535'],
            'allowsInfantsOnTop' => ['boolean'],
            'isEntireUnit' => ['boolean'],
            'smokingAllowed' => ['boolean'],
            'isFeatured' => ['boolean'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
            'amenityIds' => ['array'],
            'amenityIds.*' => ['integer', 'distinct', Rule::exists('property_booking_amenities', 'id')],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'propertyId' => 'property', 'shortDescription' => 'short description',
            'sizeSquareMetres' => 'floor area', 'bedroomCount' => 'bedrooms',
            'bathroomCount' => 'bathrooms', 'livingRoomCount' => 'living rooms',
            'bedCount' => 'bed count', 'bedConfigurationJson' => 'bed configuration',
            'maximumGuests' => 'maximum guests', 'maximumAdults' => 'maximum adults',
            'maximumChildren' => 'maximum children', 'maximumInfants' => 'maximum infants',
            'allowsInfantsOnTop' => 'infant capacity behavior', 'isEntireUnit' => 'entire unit setting',
            'smokingAllowed' => 'smoking setting', 'isFeatured' => 'featured setting',
            'metaTitle' => 'meta title', 'metaDescription' => 'meta description',
            'amenityIds' => 'amenities',
        ];
    }

    /** Populate every editable unit-type field and amenity selector. */
    public function fillFromUnitType(UnitType $unitType): void
    {
        $unitType->loadMissing('amenities');
        $this->unitType = $unitType;
        $this->propertyId = (string) $unitType->property_id;
        $this->code = $unitType->code;
        $this->slug = $unitType->slug;
        $this->name = $unitType->name;
        $this->shortDescription = (string) $unitType->short_description;
        $this->description = (string) $unitType->description;
        $this->sizeSquareMetres = (string) $unitType->size_square_metres;
        $this->bedroomCount = $unitType->bedroom_count;
        $this->bathroomCount = $unitType->bathroom_count;
        $this->livingRoomCount = $unitType->living_room_count;
        $this->bedCount = $unitType->bed_count;
        $this->bedConfigurationJson = $unitType->bed_configuration === null
            ? ''
            : (string) json_encode($unitType->bed_configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->maximumGuests = $unitType->maximum_guests;
        $this->maximumAdults = $unitType->maximum_adults;
        $this->maximumChildren = $unitType->maximum_children;
        $this->maximumInfants = $unitType->maximum_infants;
        $this->allowsInfantsOnTop = $unitType->allows_infants_on_top;
        $this->isEntireUnit = $unitType->is_entire_unit;
        $this->smokingAllowed = $unitType->smoking_allowed;
        $this->isFeatured = $unitType->is_featured;
        $this->metaTitle = (string) $unitType->meta_title;
        $this->metaDescription = (string) $unitType->meta_description;
        $this->amenityIds = $unitType->amenities->pluck('id')->all();
    }

    /** Reset to unit-type creation defaults for a selected property. */
    public function resetForCreate(?int $propertyId = null): void
    {
        $this->reset();
        $this->unitType = null;
        $this->propertyId = $propertyId === null ? '' : (string) $propertyId;
        $this->bathroomCount = 1;
        $this->bedCount = 1;
        $this->bedConfigurationJson = "[\n    {\"type\": \"queen\", \"quantity\": 1}\n]";
        $this->maximumGuests = 1;
        $this->maximumAdults = 1;
        $this->allowsInfantsOnTop = true;
        $this->isEntireUnit = true;
        $this->amenityIds = [];
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'code' => strtoupper(trim($this->code)),
            'slug' => Str::slug($this->slug !== '' ? $this->slug : $this->name),
            'name' => trim($this->name),
            'short_description' => self::nullableTrimmed($this->shortDescription),
            'description' => self::nullableTrimmed($this->description),
            'size_square_metres' => self::nullableTrimmed($this->sizeSquareMetres),
            'bedroom_count' => $this->bedroomCount,
            'bathroom_count' => $this->bathroomCount,
            'living_room_count' => $this->livingRoomCount,
            'bed_count' => $this->bedCount,
            'bed_configuration' => $this->bedConfigurationJson === ''
                ? null
                : json_decode($this->bedConfigurationJson, true, 512, JSON_THROW_ON_ERROR),
            'maximum_guests' => $this->maximumGuests,
            'maximum_adults' => $this->maximumAdults,
            'maximum_children' => $this->maximumChildren,
            'maximum_infants' => $this->maximumInfants,
            'allows_infants_on_top' => $this->allowsInfantsOnTop,
            'is_entire_unit' => $this->isEntireUnit,
            'smoking_allowed' => $this->smokingAllowed,
            'is_featured' => $this->isFeatured,
            'meta_title' => self::nullableTrimmed($this->metaTitle),
            'meta_description' => self::nullableTrimmed($this->metaDescription),
        ];
    }

    /** Normalize optional text for persistence. */
    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
