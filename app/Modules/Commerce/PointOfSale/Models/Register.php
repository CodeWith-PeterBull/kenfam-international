<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Models;

use App\Modules\Commerce\Database\Factories\RegisterFactory;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configured physical or logical POS register endpoint.
 */
final class Register extends Model
{
    /** @use HasFactory<RegisterFactory> */
    use HasFactory;

    use HasUlid;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'location_label',
        'description',
        'receipt_print_driver',
        'receipt_print_mode',
        'receipt_paper_width',
        'receipt_printer_name',
        'is_active',
    ];

    /**
     * Cast register activation state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'receipt_print_mode' => ReceiptPrintMode::class,
            'receipt_paper_width' => ReceiptPaperWidth::class,
        ];
    }

    /**
     * Create a module-local register factory instance.
     */
    protected static function newFactory(): RegisterFactory
    {
        return RegisterFactory::new();
    }

    /**
     * Till sessions opened on this register.
     */
    public function tillSessions(): HasMany
    {
        return $this->hasMany(TillSession::class);
    }

    /**
     * POS orders processed by this register.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
