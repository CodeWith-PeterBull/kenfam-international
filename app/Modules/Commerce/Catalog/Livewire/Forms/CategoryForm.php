<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Livewire\Forms;

use App\Modules\Commerce\Catalog\Models\ProductCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validates hierarchical product-category administration input.
 */
final class CategoryForm extends Form
{
    public ?ProductCategory $category = null;

    public string $parentId = '';

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public string $metaTitle = '';

    public string $metaDescription = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'parentId' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('product_categories', 'slug')->ignore($this->category)],
            'description' => ['nullable', 'string', 'max:5000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'parentId' => 'parent category',
            'sortOrder' => 'sort order',
            'isActive' => 'active status',
            'metaTitle' => 'meta title',
            'metaDescription' => 'meta description',
        ];
    }

    public function fillFromCategory(ProductCategory $category): void
    {
        $this->category = $category;
        $this->parentId = $category->parent_id === null ? '' : (string) $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = (string) $category->description;
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
        $this->metaTitle = (string) $category->meta_title;
        $this->metaDescription = (string) $category->meta_description;
    }

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
            'meta_title' => self::nullableTrimmed($this->metaTitle),
            'meta_description' => self::nullableTrimmed($this->metaDescription),
        ];
    }

    private static function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
