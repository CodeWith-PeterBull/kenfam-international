<?php

/** Validates tour-owned category and destination route assignments. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\TourAssignmentData;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/** Carry only target IDs and route presentation values through Livewire. */
final class TourAssignmentForm extends Form
{
    /** @var list<int|string> */
    public array $categoryIds = [];

    public string $primaryCategoryId = '';

    /** @var list<array{destination_id: int|string, role: string, is_overnight: bool}> */
    public array $destinationRows = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'categoryIds' => ['array', 'max:50'],
            'categoryIds.*' => ['integer', 'distinct', Rule::exists('travel_tour_categories', 'id')->where('is_active', true)],
            'primaryCategoryId' => ['nullable', 'integer'],
            'destinationRows' => ['array', 'max:100'],
            'destinationRows.*.destination_id' => ['required', 'integer', 'distinct', Rule::exists('travel_destinations', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'destinationRows.*.role' => ['required', Rule::in(['primary', 'start', 'visit', 'overnight', 'end'])],
            'destinationRows.*.is_overnight' => ['boolean'],
        ];
    }

    /** Fill controls from one authorized tour's current route. */
    public function fillFromTour(Tour $tour): void
    {
        $tour->loadMissing(['categories', 'destinations']);
        $this->categoryIds = $tour->categories->sortBy('pivot.sort_order')->pluck('id')->all();
        $this->primaryCategoryId = (string) ($tour->categories->first(fn ($category) => $category->pivot->is_primary)?->id ?? '');
        $this->destinationRows = $tour->destinations->sortBy('pivot.sequence')->map(fn ($destination) => [
            'destination_id' => $destination->id,
            'role' => $destination->pivot->role,
            'is_overnight' => (bool) $destination->pivot->is_overnight,
        ])->values()->all();
    }

    /** Build the typed complete replacement set. */
    public function toData(): TourAssignmentData
    {
        $ids = array_map(intval(...), $this->categoryIds);
        if ($ids !== [] && ($this->primaryCategoryId === '' || ! in_array((int) $this->primaryCategoryId, $ids, true))) {
            throw ValidationException::withMessages(['assignments.primaryCategoryId' => 'Choose one of the selected categories as primary.']);
        }

        return new TourAssignmentData(
            categories: array_map(fn ($id, $index) => [
                'category_id' => $id,
                'is_primary' => $id === (int) $this->primaryCategoryId,
                'sort_order' => $index + 1,
            ], $ids, array_keys($ids)),
            destinations: array_map(fn ($row, $index) => [
                'destination_id' => (int) $row['destination_id'],
                'role' => $row['role'], 'sequence' => $index + 1,
                'is_overnight' => (bool) $row['is_overnight'],
            ], $this->destinationRows, array_keys($this->destinationRows)),
        );
    }
}
