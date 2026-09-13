<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Models;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product, cost, selling-price, discount, and tax snapshot within an order.
 */
final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Cast quantity, money, rate, and tax-mode snapshots.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'unit_cost_minor' => 'integer',
            'line_subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'is_tax_inclusive' => 'boolean',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    /**
     * Create a module-local order-item factory instance.
     */
    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /**
     * Order aggregate that owns this line.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Current product link, which may be null after product deletion.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
