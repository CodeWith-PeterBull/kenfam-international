<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates reusable accommodation amenity input. */
final class AmenityForm extends Form
{
    public ?Amenity $amenity = null;

    public string $name = '';

    public string $slug = '';

    public string $scope = 'both';

    public string $description = '';

    public string $iconKey = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('property_booking_amenities', 'name')->ignore($this->amenity)],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('property_booking_amenities', 'slug')->ignore($this->amenity)],
            'scope' => ['required', Rule::enum(AmenityScope::class)],
            'description' => ['nullable', 'string', 'max:10000'],
            'iconKey' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['iconKey' => 'icon key', 'sortOrder' => 'sort order', 'isActive' => 'active status'];
    }

    /** Populate editable values from an existing amenity. */
    public function fillFromAmenity(Amenity $amenity): void
    {
        $this->amenity = $amenity;
        $this->name = $amenity->name;
        $this->slug = $amenity->slug;
        $this->scope = $amenity->scope->value;
        $this->description = (string) $amenity->description;
        $this->iconKey = (string) $amenity->icon_key;
        $this->sortOrder = $amenity->sort_order;
        $this->isActive = $amenity->is_active;
    }

    /** Reset to amenity creation defaults. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->amenity = null;
        $this->scope = AmenityScope::Both->value;
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'name' => trim($this->name),
            'slug' => Str::slug($this->slug !== '' ? $this->slug : $this->name),
            'scope' => $this->scope,
            'description' => self::nullableTrimmed($this->description),
            'icon_key' => self::nullableTrimmed($this->iconKey),
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }

    /** Normalize optional text for persistence. */
    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
