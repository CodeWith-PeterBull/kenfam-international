<?php

/** Validates activity inputs before creating typed service data. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\ItineraryActivityData;
use App\Modules\TravelTours\Catalog\Models\ItineraryActivity;
use Livewire\Form;

/** Collect local time, location and inclusion details for one activity. */
final class ItineraryActivityForm extends Form
{
    public string $title = '';

    public string $description = '';

    public string $startsAtLocal = '';

    public string $endsAtLocal = '';

    public string $locationName = '';

    public string $destinationId = '';

    public string $latitude = '';

    public string $longitude = '';

    public bool $isIncluded = true;

    public bool $isOptional = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'startsAtLocal' => ['nullable', 'date_format:H:i'],
            'endsAtLocal' => ['nullable', 'date_format:H:i', 'after_or_equal:startsAtLocal'],
            'locationName' => ['nullable', 'string', 'max:200'],
            'destinationId' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'isIncluded' => ['boolean'], 'isOptional' => ['boolean'],
        ];
    }

    /** Load an authorized activity into the inline editor. */
    public function fillFromActivity(ItineraryActivity $activity): void
    {
        $this->title = $activity->title;
        $this->description = (string) $activity->description;
        $this->startsAtLocal = $activity->starts_at_local === null ? '' : substr((string) $activity->starts_at_local, 0, 5);
        $this->endsAtLocal = $activity->ends_at_local === null ? '' : substr((string) $activity->ends_at_local, 0, 5);
        $this->locationName = (string) $activity->location_name;
        $this->destinationId = (string) ($activity->destination_id ?? '');
        $this->latitude = (string) $activity->latitude;
        $this->longitude = (string) $activity->longitude;
        $this->isIncluded = $activity->is_included;
        $this->isOptional = $activity->is_optional;
    }

    /** Restore blank activity defaults after a write. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->isIncluded = true;
    }

    /** Convert a validated form to the immutable activity contract. */
    public function toData(): ItineraryActivityData
    {
        return new ItineraryActivityData(
            title: $this->title, description: self::optional($this->description),
            startsAtLocal: self::optional($this->startsAtLocal), endsAtLocal: self::optional($this->endsAtLocal),
            locationName: self::optional($this->locationName),
            destinationId: $this->destinationId === '' ? null : (int) $this->destinationId,
            latitude: self::optional($this->latitude), longitude: self::optional($this->longitude),
            isIncluded: $this->isIncluded, isOptional: $this->isOptional,
        );
    }

    /** Normalize blank optional text. */
    private static function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
