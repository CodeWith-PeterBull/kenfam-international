<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Models;

use App\Models\User;
use App\Modules\Commerce\Database\Factories\TillSessionFactory;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service-controlled cashier session and closing cash reconciliation snapshot.
 */
final class TillSession extends Model
{
    /** @use HasFactory<TillSessionFactory> */
    use HasFactory;

    use HasUlid;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Cast lifecycle, money snapshots, and timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TillSessionStatus::class,
            'opening_float_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Create a module-local till-session factory instance.
     */
    protected static function newFactory(): TillSessionFactory
    {
        return TillSessionFactory::new();
    }

    /**
     * Register operated during this session.
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * Cashier who opened the session.
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * User who closed the session, when closed.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * POS orders completed or held in this session.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Payment tenders recorded in this session.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Determine whether the session may currently accept transactions.
     */
    public function isOpen(): bool
    {
        return $this->status === TillSessionStatus::Open && $this->closed_at === null;
    }
}
