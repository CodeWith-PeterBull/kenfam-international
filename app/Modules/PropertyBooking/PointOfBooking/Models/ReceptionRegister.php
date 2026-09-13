<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Database\Factories\ReceptionRegisterFactory;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Property-scoped reception endpoint and receipt-printer configuration. */
final class ReceptionRegister extends Model
{
    /** @use HasFactory<ReceptionRegisterFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $table = 'property_booking_registers';

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'code', 'name', 'location_label', 'description',
        'receipt_print_driver', 'receipt_print_mode', 'receipt_paper_width',
        'receipt_printer_name', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'receipt_print_mode' => ReceiptPrintMode::class,
            'receipt_paper_width' => ReceiptPaperWidth::class,
            'is_active' => 'boolean',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): ReceptionRegisterFactory
    {
        return ReceptionRegisterFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the creator relationship. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Get the updater relationship. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Get the shifts relationship. */
    public function shifts(): HasMany
    {
        return $this->hasMany(ReceptionShift::class, 'register_id');
    }

    /** Get the bookings relationship. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'register_id');
    }

    /** Constrain the query to active records. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
