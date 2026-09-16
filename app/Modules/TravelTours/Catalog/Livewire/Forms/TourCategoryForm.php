<?php

/** Validates editable TravelTours category fields at the Livewire boundary. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\TourCategoryData;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Convert presentation values into the immutable category service contract. */
final class TourCategoryForm extends Form
{
    public string $name = '';

    public string $slug = '';

    public string $parentId = '';

    public string $description = '';

    public string $iconKey = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'parentId' => ['nullable', 'integer', Rule::exists('travel_tour_categories', 'id')],
            'description' => ['nullable', 'string', 'max:10000'],
            'iconKey' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** Populate the form from an authorized category. */
    public function fillFromCategory(TourCategory $category): void
    {
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->parentId = $category->parent_id === null ? '' : (string) $category->parent_id;
        $this->description = (string) $category->description;
        $this->iconKey = (string) $category->icon_key;
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
        $this->metaTitle = (string) $category->meta_title;
        $this->metaDescription = (string) $category->meta_description;
    }

    /** Restore creation defaults after closing the editor. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }

    /** Build a typed request after Livewire validation succeeds. */
    public function toData(): TourCategoryData
    {
        return new TourCategoryData(
            name: trim($this->name),
            slug: self::optional($this->slug),
            parentId: $this->parentId === '' ? null : (int) $this->parentId,
            description: self::optional($this->description),
            iconKey: self::optional($this->iconKey),
            sortOrder: $this->sortOrder,
            isActive: $this->isActive,
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
