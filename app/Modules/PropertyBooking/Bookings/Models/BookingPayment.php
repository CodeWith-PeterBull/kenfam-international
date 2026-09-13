<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Database\Factories\BookingPaymentFactory;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manual or adapter-ready tender recorded against a booking. */
final class BookingPayment extends Model
{
    /** @use HasFactory<BookingPaymentFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_payments';

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => BookingPaymentMethod::class, 'status' => BookingPaymentRecordStatus::class,
            'amount_minor' => 'integer', 'tendered_minor' => 'integer', 'change_minor' => 'integer',
            'refunded_amount_minor' => 'integer', 'metadata' => 'array',
            'paid_at' => 'datetime', 'failed_at' => 'datetime', 'refunded_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): BookingPaymentFactory
    {
        return BookingPaymentFactory::new();
    }

    /** Get the booking relationship. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** Get the reception shift relationship. */
    public function receptionShift(): BelongsTo
    {
        return $this->belongsTo(ReceptionShift::class, 'reception_shift_id');
    }

    /** Get the recorder relationship. */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Constrain the query to completed records. */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', BookingPaymentRecordStatus::Completed->value);
    }
}
