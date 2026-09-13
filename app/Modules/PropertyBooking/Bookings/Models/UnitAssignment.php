<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Database\Factories\UnitAssignmentFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Authoritative exact-unit allocation consuming availability for one stay. */
final class UnitAssignment extends Model
{
    /** @use HasFactory<UnitAssignmentFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_unit_assignments';

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UnitAssignmentStatus::class,
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'assigned_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): UnitAssignmentFactory
    {
        return UnitAssignmentFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the booking relationship. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** Get the booking stay relationship. */
    public function bookingStay(): BelongsTo
    {
        return $this->belongsTo(BookingStay::class, 'booking_stay_id');
    }

    /** Get the unit relationship. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'unit_id');
    }

    /** Get the assigner relationship. */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Get the releaser relationship. */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Constrain the query to active records. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UnitAssignmentStatus::Active->value);
    }

    /** Constrain the query to overlapping records. */
    public function scopeOverlapping(Builder $query, DateTimeInterface $startsAt, DateTimeInterface $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
