<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
final class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * Define a non-negative product stock projection.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'on_hand' => fake()->numberBetween(0, 100),
            'low_stock_threshold' => (int) config('commerce.inventory.default_low_stock_threshold', 5),
            'updated_by' => null,
        ];
    }
}
