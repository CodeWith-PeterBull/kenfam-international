<?php

/** Validates editable TravelTours destination fields at the Livewire boundary. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\DestinationData;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Destination;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Convert presentation values into the immutable destination service contract. */
final class DestinationForm extends Form
{
    public string $type = 'country';

    public string $name = '';

    public string $slug = '';

    public string $parentId = '';

    public string $countryCode = '';

    public string $code = '';

    public string $shortDescription = '';

    public string $description = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $timezone = '';

    public bool $isFeatured = false;

    public bool $isActive = true;

    public string $status = 'draft';

    public int $sortOrder = 0;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DestinationType::class)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'parentId' => ['nullable', 'integer', Rule::exists('travel_destinations', 'id')->whereNull('deleted_at')],
            'countryCode' => ['required_unless:type,continent,region', 'nullable', 'string', 'size:2', 'alpha'],
            'code' => ['nullable', 'string', 'max:40'],
            'shortDescription' => ['nullable', 'string', 'max:320'],
            'description' => ['nullable', 'string', 'max:50000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'timezone', 'max:64'],
            'isFeatured' => ['boolean'],
            'isActive' => ['boolean'],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Populate the form from an authorized destination. */
    public function fillFromDestination(Destination $destination): void
    {
        $this->type = $destination->type->value;
        $this->name = $destination->name;
        $this->slug = $destination->slug;
        $this->parentId = $destination->parent_id === null ? '' : (string) $destination->parent_id;
        $this->countryCode = (string) $destination->country_code;
        $this->code = (string) $destination->code;
        $this->shortDescription = (string) $destination->short_description;
        $this->description = (string) $destination->description;
        $this->latitude = (string) $destination->latitude;
        $this->longitude = (string) $destination->longitude;
        $this->timezone = (string) $destination->timezone;
        $this->isFeatured = $destination->is_featured;
        $this->isActive = $destination->is_active;
        $this->status = $destination->status->value;
        $this->sortOrder = $destination->sort_order;
        $this->metaTitle = (string) $destination->meta_title;
        $this->metaDescription = (string) $destination->meta_description;
    }

    /** Restore creation defaults after closing the editor. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->type = DestinationType::Country->value;
        $this->status = PublicationStatus::Draft->value;
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }

    /** Build a typed request after Livewire validation succeeds. */
    public function toData(): DestinationData
    {
        return new DestinationData(
            type: DestinationType::from($this->type),
            name: trim($this->name),
            slug: self::optional($this->slug),
            parentId: $this->parentId === '' ? null : (int) $this->parentId,
            countryCode: self::optional($this->countryCode),
            code: self::optional($this->code),
            shortDescription: self::optional($this->shortDescription),
            description: self::optional($this->description),
            latitude: self::optional($this->latitude),
            longitude: self::optional($this->longitude),
            timezone: self::optional($this->timezone),
            isFeatured: $this->isFeatured,
            isActive: $this->isActive,
            status: PublicationStatus::from($this->status),
            sortOrder: $this->sortOrder,
            metaTitle: self::optional($this->metaTitle),
            metaDescription: self::optional($this->metaDescription),
        );
    }

    /** Return null for an empty optional text field. */
    private static function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
