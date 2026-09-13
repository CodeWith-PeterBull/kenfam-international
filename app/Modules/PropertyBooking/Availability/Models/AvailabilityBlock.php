<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Database\Factories\AvailabilityBlockFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Auditable reason a concrete unit cannot be allocated for an interval. */
final class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_availability_blocks';

    /** @var list<string> */
    protected $fillable = ['property_id', 'unit_id', 'type', 'starts_at', 'ends_at', 'reason', 'internal_note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AvailabilityBlockType::class,
            'status' => AvailabilityBlockStatus::class,
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): AvailabilityBlockFactory
    {
        return AvailabilityBlockFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the unit relationship. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'unit_id');
    }

    /** Get the creator relationship. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Get the releaser relationship. */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Constrain the query to active records. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AvailabilityBlockStatus::Active->value);
    }

    /** Constrain the query to overlapping records. */
    public function scopeOverlapping(Builder $query, DateTimeInterface $startsAt, DateTimeInterface $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
