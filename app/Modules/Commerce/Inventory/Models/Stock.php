<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Models;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fast one-row projection of a product's current inventory balance.
 *
 * Balance changes are service controlled and must be accompanied by a matching
 * StockMovement ledger row in the same transaction.
 */
final class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'low_stock_threshold',
    ];

    /**
     * Cast inventory quantities as integers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /**
     * Create a module-local stock factory instance.
     */
    protected static function newFactory(): StockFactory
    {
        return StockFactory::new();
    }

    /**
     * Product represented by this stock projection.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * User responsible for the most recent manual projection update.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Return available sellable units for the single-location first release.
     */
    public function available(): int
    {
        return $this->on_hand;
    }

    /**
     * Determine whether the current balance has reached its warning threshold.
     */
    public function isLow(): bool
    {
        return $this->on_hand <= $this->low_stock_threshold;
    }
}
