<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Models;

use App\Models\User;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Database\Factories\OrderFactory;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Service-controlled aggregate shared by web orders, held POS carts, and POS sales.
 */
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUlid;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Cast status enums, monetary snapshots, flags, and lifecycle timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'fulfillment_type' => FulfillmentType::class,
            'preferred_payment_method' => PaymentMethod::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'tax_inclusive' => 'boolean',
            'stock_committed_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Create a module-local order factory instance.
     */
    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * Reusable customer linked to this immutable order snapshot.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Optional authenticated storefront account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * POS register used for this order.
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * POS till session responsible for this order.
     */
    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
    }

    /**
     * Cashier who completed the POS transaction.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Staff user who created or entered the order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Immutable product and pricing snapshots.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Pending and completed payment records.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Inventory movements caused by placement or cancellation.
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    /**
     * Expose the immutable customer snapshot as a display name.
     */
    protected function customerDisplayName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->customer_first_name.' '.$this->customer_last_name));
    }

    /**
     * Expose the remaining amount due in minor units.
     */
    protected function balanceDueMinor(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->total_minor - $this->paid_minor));
    }

    /**
     * Determine whether completed payments settle the order total.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_minor >= $this->total_minor
            && $this->payment_status === OrderPaymentStatus::Paid;
    }

    /**
     * Restrict a query to orders from one transaction channel.
     */
    public function scopeForChannel(Builder $query, OrderChannel $channel): Builder
    {
        return $query->where('channel', $channel->value);
    }
}
