<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Models;

use App\Models\User;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Database\Factories\ProductFactory;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Shared simple product containing storefront, POS, pricing, and stock policy data.
 */
final class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUlid;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'manufacturer_barcode',
        'status',
        'short_description',
        'description',
        'specifications',
        'unit_label',
        'minimum_order_quantity',
        'maximum_order_quantity',
        'price_minor',
        'sale_price_minor',
        'sale_starts_at',
        'sale_ends_at',
        'cost_price_minor',
        'tax_rate_bps',
        'is_tax_inclusive',
        'track_stock',
        'weight_grams',
        'length_mm',
        'width_mm',
        'height_mm',
        'is_featured',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    /**
     * Cast product policy, structured metadata, and lifecycle values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'specifications' => 'array',
            'minimum_order_quantity' => 'integer',
            'maximum_order_quantity' => 'integer',
            'price_minor' => 'integer',
            'sale_price_minor' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'cost_price_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'is_tax_inclusive' => 'boolean',
            'track_stock' => 'boolean',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Create a module-local product factory instance.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * Register the ordered product gallery used by cards and detail pages.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    /**
     * Primary catalog category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Current one-row inventory projection.
     */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    /**
     * Immutable inventory ledger events for this product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Historical order lines that reference this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Administrator who originally created the product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Administrator who most recently updated the product.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Determine whether promotional pricing is active at the current time.
     */
    public function isOnSale(): bool
    {
        if ($this->sale_price_minor === null) {
            return false;
        }

        if ($this->sale_starts_at?->isFuture() === true) {
            return false;
        }

        return $this->sale_ends_at?->isPast() !== true;
    }

    /**
     * Return the whole-number promotional reduction used by catalog badges.
     */
    public function saleDiscountPercentage(): ?int
    {
        if (! $this->isOnSale() || $this->price_minor <= 0) {
            return null;
        }

        return intdiv(($this->price_minor - $this->sale_price_minor) * 100, $this->price_minor);
    }

    /**
     * Determine whether the product may satisfy at least one unit.
     */
    public function isInStock(): bool
    {
        return ! $this->track_stock || ($this->stock?->on_hand ?? 0) > 0;
    }

    /**
     * Expose the currently effective unit price in minor units.
     */
    protected function effectivePriceMinor(): Attribute
    {
        return Attribute::get(fn (): int => $this->isOnSale()
            ? (int) $this->sale_price_minor
            : (int) $this->price_minor);
    }

    /**
     * Restrict a query to products currently eligible for public catalog display.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Published->value)
            ->where(function (Builder $publication): void {
                $publication->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Restrict public discovery to published products in active categories.
     *
     * Published uncategorized products remain visible so catalog maintenance
     * does not require a category assignment before a valid public release.
     */
    public function scopeVisibleInStorefront(Builder $query): Builder
    {
        return $query
            ->published()
            ->where(function (Builder $visibility): void {
                $visibility
                    ->whereNull('category_id')
                    ->orWhereHas('category', static fn (Builder $category): Builder => $category->active());
            });
    }

    /**
     * Determine whether this product currently satisfies the storefront scope.
     *
     * The loaded category is reused by administration tables to avoid one
     * visibility query per row. Other callers may use the method safely without
     * preloading the relationship.
     */
    public function isVisibleInStorefront(): bool
    {
        if ($this->status !== ProductStatus::Published || $this->published_at?->isFuture() === true) {
            return false;
        }

        if ($this->category_id === null) {
            return true;
        }

        if ($this->relationLoaded('category')) {
            return $this->category?->is_active === true;
        }

        return $this->category()->active()->exists();
    }

    /**
     * Restrict a query to products with an active promotional price.
     */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query
            ->whereNotNull('sale_price_minor')
            ->where(function (Builder $starts): void {
                $starts->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now());
            })
            ->where(function (Builder $ends): void {
                $ends->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now());
            });
    }

    /**
     * Filter using the active sale price when one exists, otherwise regular price.
     */
    public function scopeWhereEffectivePriceBetween(Builder $query, ?int $minimum, ?int $maximum): Builder
    {
        if ($minimum === null && $maximum === null) {
            return $query;
        }

        $now = now();
        $expression = self::effectivePriceExpression();
        $bindings = [$now, $now];

        if ($minimum !== null && $maximum !== null) {
            return $query->whereRaw("{$expression} BETWEEN ? AND ?", [...$bindings, $minimum, $maximum]);
        }

        if ($minimum !== null) {
            return $query->whereRaw("{$expression} >= ?", [...$bindings, $minimum]);
        }

        return $query->whereRaw("{$expression} <= ?", [...$bindings, $maximum]);
    }

    /**
     * Sort by the same effective price exposed by the model accessor.
     */
    public function scopeOrderByEffectivePrice(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $now = now();

        return $query->orderByRaw(self::effectivePriceExpression()." {$direction}", [$now, $now]);
    }

    /**
     * Return the portable SQL expression matching isOnSale and effectivePriceMinor.
     */
    private static function effectivePriceExpression(): string
    {
        return 'CASE WHEN sale_price_minor IS NOT NULL '
            .'AND (sale_starts_at IS NULL OR sale_starts_at <= ?) '
            .'AND (sale_ends_at IS NULL OR sale_ends_at >= ?) '
            .'THEN sale_price_minor ELSE price_minor END';
    }
}
