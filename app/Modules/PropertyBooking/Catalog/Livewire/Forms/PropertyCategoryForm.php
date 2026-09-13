<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Forms;

use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Validates and normalizes hierarchical property-category input. */
final class PropertyCategoryForm extends Form
{
    public ?PropertyCategory $category = null;

    public string $parentId = '';

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'parentId' => ['nullable', 'integer', Rule::exists('property_booking_categories', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('property_booking_categories', 'slug')->ignore($this->category)],
            'description' => ['nullable', 'string', 'max:10000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['parentId' => 'parent category', 'sortOrder' => 'sort order', 'isActive' => 'active status'];
    }

    /** Populate editable values from an existing category. */
    public function fillFromCategory(PropertyCategory $category): void
    {
        $this->category = $category;
        $this->parentId = $category->parent_id === null ? '' : (string) $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = (string) $category->description;
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
    }

    /** Reset to category creation defaults. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->category = null;
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'parent_id' => $this->parentId === '' ? null : (int) $this->parentId,
            'name' => trim($this->name),
            'slug' => Str::slug($this->slug !== '' ? $this->slug : $this->name),
            'description' => self::nullableTrimmed($this->description),
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
