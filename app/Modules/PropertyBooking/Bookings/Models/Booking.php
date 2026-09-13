<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Database\Factories\BookingFactory;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Reservation aggregate owning lifecycle, snapshots, totals, and channel context. */
final class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_bookings';

    /** Aggregate fields are written only by booking-domain services. */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => BookingChannel::class, 'status' => BookingStatus::class,
            'stay_status' => StayStatus::class, 'payment_status' => BookingPaymentStatus::class,
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'adult_count' => 'integer', 'child_count' => 'integer', 'infant_count' => 'integer',
            'accommodation_subtotal_minor' => 'integer', 'charges_subtotal_minor' => 'integer',
            'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer',
            'required_deposit_minor' => 'integer', 'paid_minor' => 'integer', 'tax_inclusive' => 'boolean',
            'hold_expires_at' => 'datetime', 'pending_expires_at' => 'datetime',
            'placed_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime',
            'completed_at' => 'datetime', 'hold_expiring_notified_at' => 'datetime',
            'arrival_due_notified_at' => 'datetime', 'departure_due_notified_at' => 'datetime',
            'preferred_payment_method' => BookingPaymentMethod::class,
            'terms_accepted_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the primary guest relationship. */
    public function primaryGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'primary_guest_id');
    }

    /** Get the register relationship. */
    public function register(): BelongsTo
    {
        return $this->belongsTo(ReceptionRegister::class, 'register_id');
    }

    /** Get the reception shift relationship. */
    public function receptionShift(): BelongsTo
    {
        return $this->belongsTo(ReceptionShift::class, 'reception_shift_id');
    }

    /** Get the receptionist relationship. */
    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionist_id');
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

    /** Get the stays relationship. */
    public function stays(): HasMany
    {
        return $this->hasMany(BookingStay::class, 'booking_id')->orderBy('line_number');
    }

    /** Get the unit assignments relationship. */
    public function unitAssignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class, 'booking_id');
    }

    /** Get the guest assignments relationship. */
    public function guestAssignments(): HasMany
    {
        return $this->hasMany(BookingGuest::class, 'booking_id');
    }

    /** Get the guests relationship. */
    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'property_booking_guest_assignments')
            ->using(BookingGuest::class)
            ->withPivot(['booking_stay_id', 'is_primary', 'primary_booking_guard', 'checked_in_at', 'checked_out_at'])
            ->withTimestamps();
    }

    /** Get the charges relationship. */
    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class, 'booking_id');
    }

    /** Get the payments relationship. */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'booking_id');
    }

    /** Constrain the query to consuming availability records. */
    public function scopeConsumingAvailability(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BookingStatus::Held->value,
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
        ]);
    }

    /** Determine whether the model is availability consuming. */
    public function isAvailabilityConsuming(): bool
    {
        return $this->status->consumesAvailability();
    }

    /** Derived non-negative amount still owed by the guest. */
    protected function balanceMinor(): Attribute
    {
        return Attribute::get(fn (): int => max((int) $this->total_minor - (int) $this->paid_minor, 0));
    }
}
