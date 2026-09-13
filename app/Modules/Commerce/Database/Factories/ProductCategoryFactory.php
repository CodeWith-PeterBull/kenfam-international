<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Catalog\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
final class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * Define an active root catalog category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999999),
            'description' => fake()->sentence(),
            'meta_title' => null,
            'meta_description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Nest the generated category beneath a parent category.
     */
    public function childOf(ProductCategory|ProductCategoryFactory $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent,
        ]);
    }
}
