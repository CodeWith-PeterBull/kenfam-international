<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Livewire\Forms;

use App\Modules\PropertyBooking\Storefront\Data\StaySearchData;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates bounded public dates, times, occupancy, category, and location. */
final class StaySearchForm extends Form
{
    public string $arrivalDate = '';

    public string $departureDate = '';

    public string $arrivalTime = '15:00';

    public string $departureTime = '10:00';

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    public string $category = '';

    public string $location = '';

    /** Return public search validation rules. */
    protected function rules(): array
    {
        return [
            'arrivalDate' => ['required', 'date_format:Y-m-d'],
            'departureDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:arrivalDate'],
            'arrivalTime' => ['required', 'date_format:H:i'],
            'departureTime' => ['required', 'date_format:H:i'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'infants' => ['required', 'integer', 'min:0', 'max:10'],
            'category' => ['nullable', 'string', 'max:180', Rule::exists('property_booking_categories', 'slug')->where('is_active', true)],
            'location' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Return guest-facing validation labels. */
    protected function validationAttributes(): array
    {
        return [
            'arrivalDate' => 'arrival date',
            'departureDate' => 'departure date',
            'arrivalTime' => 'arrival time',
            'departureTime' => 'departure time',
        ];
    }

    /** Fill stable tomorrow/day-after defaults. */
    public function fillDefaults(): void
    {
        $this->arrivalDate = now()->addDay()->toDateString();
        $this->departureDate = now()->addDays(2)->toDateString();
    }

    /** Convert validated form state into an immutable search DTO. */
    public function toData(): StaySearchData
    {
        return new StaySearchData(
            arrivalDate: $this->arrivalDate,
            departureDate: $this->departureDate,
            arrivalTime: $this->arrivalTime,
            departureTime: $this->departureTime,
            adults: $this->adults,
            children: $this->children,
            infants: $this->infants,
            categorySlug: $this->nullable($this->category),
            location: $this->nullable($this->location),
        );
    }

    /** Normalize an optional text filter. */
    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
