<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Models;

use App\Models\User;
use App\Modules\Commerce\Database\Factories\PaymentFactory;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service-controlled payment preference, confirmation, or POS tender record.
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUlid;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Cast payment enums, monetary values, sanitized metadata, and timestamp.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'tendered_minor' => 'integer',
            'change_minor' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Create a module-local payment factory instance.
     */
    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * Order settled or intended to be settled by this record.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * POS till session that received this tender.
     */
    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
    }

    /**
     * Staff user who recorded or confirmed the payment.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Determine whether the payment contributes to the order paid projection.
     */
    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed && $this->paid_at !== null;
    }
}
