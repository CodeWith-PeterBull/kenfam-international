<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * Define a standalone opening-balance ledger event.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 30);

        return [
            'product_id' => Product::factory(),
            'type' => StockMovementType::Opening,
            'quantity_delta' => $quantity,
            'balance_before' => 0,
            'balance_after' => $quantity,
            'reference_type' => null,
            'reference_id' => null,
            'note' => 'Factory opening balance',
            'created_by' => null,
            'created_at' => now(),
        ];
    }
}
