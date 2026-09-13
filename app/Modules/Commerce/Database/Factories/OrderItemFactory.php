<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define a complete immutable order-line snapshot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??####??')),
            'quantity' => 2,
            'unit_price_minor' => 5_000,
            'unit_cost_minor' => 3_500,
            'line_subtotal_minor' => 10_000,
            'discount_minor' => 0,
            'tax_rate_bps' => (int) config('commerce.tax.default_rate_bps', 1600),
            'is_tax_inclusive' => (bool) config('commerce.tax.prices_include_tax', true),
            'tax_minor' => 1_379,
            'line_total_minor' => 10_000,
        ];
    }
}
