<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define a valid draft simple product.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => ProductCategory::factory(),
            'created_by' => null,
            'updated_by' => null,
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999999),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??####??')),
            'barcode' => null,
            'status' => ProductStatus::Draft,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'specifications' => ['General' => ['Condition' => 'New']],
            'unit_label' => 'item',
            'minimum_order_quantity' => 1,
            'maximum_order_quantity' => null,
            'price_minor' => fake()->numberBetween(10_000, 500_000),
            'sale_price_minor' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'cost_price_minor' => fake()->numberBetween(5_000, 9_000),
            'tax_rate_bps' => (int) config('commerce.tax.default_rate_bps', 1600),
            'is_tax_inclusive' => (bool) config('commerce.tax.prices_include_tax', true),
            'track_stock' => true,
            'weight_grams' => null,
            'length_mm' => null,
            'width_mm' => null,
            'height_mm' => null,
            'is_featured' => false,
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => null,
        ];
    }

    /**
     * Mark the product as publicly published.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Attach an active promotional price.
     */
    public function onSale(int $salePriceMinor = 9_000): static
    {
        return $this->state(fn (): array => [
            'price_minor' => max(10_000, $salePriceMinor + 1_000),
            'sale_price_minor' => $salePriceMinor,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDay(),
        ]);
    }

    /**
     * Create a stock projection after creating the product.
     */
    public function withStock(int $onHand = 10): static
    {
        return $this->afterCreating(function (Product $product) use ($onHand): void {
            Stock::factory()->for($product)->create(['on_hand' => $onHand]);
        });
    }
}
