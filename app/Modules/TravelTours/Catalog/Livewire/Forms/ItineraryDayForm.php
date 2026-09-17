<?php

/** Validates itinerary-day inputs before creating typed service data. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\ItineraryDayData;
use App\Modules\TravelTours\Catalog\Models\ItineraryDay;
use Livewire\Form;

/** Bound public day narrative and optional locations. */
final class ItineraryDayForm extends Form
{
    public string $title = '';

    public string $description = '';

    public string $meals = '';

    public string $accommodation = '';

    public string $startDestinationId = '';

    public string $endDestinationId = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:50000'],
            'meals' => ['nullable', 'string', 'max:360'],
            'accommodation' => ['nullable', 'string', 'max:240'],
            'startDestinationId' => ['nullable', 'integer', 'min:1'],
            'endDestinationId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Load only editable fields from the authorized day. */
    public function fillFromDay(ItineraryDay $day): void
    {
        $this->title = $day->title;
        $this->description = (string) $day->description;
        $this->meals = implode(', ', $day->meals ?? []);
        $this->accommodation = (string) $day->accommodation;
        $this->startDestinationId = (string) ($day->start_destination_id ?? '');
        $this->endDestinationId = (string) ($day->end_destination_id ?? '');
    }

    /** Reset the inline editor after a completed write. */
    public function resetForCreate(): void
    {
        $this->reset();
    }

    /** Convert a validated form to the immutable day contract. */
    public function toData(): ItineraryDayData
    {
        return new ItineraryDayData(
            title: $this->title, description: self::optional($this->description),
            meals: array_values(array_filter(array_map(trim(...), explode(',', $this->meals)))),
            accommodation: self::optional($this->accommodation),
            startDestinationId: $this->startDestinationId === '' ? null : (int) $this->startDestinationId,
            endDestinationId: $this->endDestinationId === '' ? null : (int) $this->endDestinationId,
        );
    }

    /** Normalize blank optional text. */
    private static function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
