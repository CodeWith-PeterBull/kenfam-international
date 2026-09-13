<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Database\Factories\ReceptionShiftFactory;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Receptionist-owned operating shift and immutable reconciliation snapshot. */
final class ReceptionShift extends Model
{
    /** @use HasFactory<ReceptionShiftFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_shifts';

    /** @var list<string> */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReceptionShiftStatus::class,
            'opening_float_minor' => 'integer', 'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer', 'variance_minor' => 'integer',
            'opened_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): ReceptionShiftFactory
    {
        return ReceptionShiftFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the register relationship. */
    public function register(): BelongsTo
    {
        return $this->belongsTo(ReceptionRegister::class, 'register_id');
    }

    /** Get the receptionist relationship. */
    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionist_id');
    }

    /** Get the opener relationship. */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** Get the closer relationship. */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Get the bookings relationship. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'reception_shift_id');
    }

    /** Get the charges relationship. */
    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class, 'reception_shift_id');
    }

    /** Get the payments relationship. */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'reception_shift_id');
    }

    /** Constrain the query to open records. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReceptionShiftStatus::Open->value);
    }

    /** Determine whether the model is open. */
    public function isOpen(): bool
    {
        return $this->status === ReceptionShiftStatus::Open;
    }
}
