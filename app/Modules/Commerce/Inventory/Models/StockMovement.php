<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Inventory\Models;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Database\Factories\StockMovementFactory;
use App\Modules\Commerce\Inventory\Enums\StockMovementType;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable stock-ledger event containing the delta and reconciled balances.
 */
final class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    use HasUlid;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Reject changes that would rewrite the inventory audit ledger.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Stock movements are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Stock movements are append-only and cannot be deleted.');
        });
    }

    /**
     * Cast movement type, quantities, and event timestamp.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_delta' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Create a module-local stock movement factory instance.
     */
    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    /**
     * Product affected by this ledger event.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Order or future aggregate that caused the movement.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Authenticated user responsible for the movement, when present.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
