<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Database\Factories\AccommodationUnitFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Concrete room, apartment, house, or space that can be allocated exactly. */
final class AccommodationUnit extends Model
{
    /** @use HasFactory<AccommodationUnitFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $table = 'property_booking_units';

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'unit_type_id', 'code', 'display_name', 'floor_label',
        'location_note', 'operational_status', 'is_active', 'internal_note', 'last_ready_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operational_status' => UnitOperationalStatus::class,
            'is_active' => 'boolean',
            'last_ready_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): AccommodationUnitFactory
    {
        return AccommodationUnitFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the unit type relationship. */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
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

    /** Get the availability blocks relationship. */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class, 'unit_id');
    }

    /** Get the assignments relationship. */
    public function assignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class, 'unit_id');
    }

    /** Constrain the query to allocatable records. */
    public function scopeAllocatable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('operational_status', UnitOperationalStatus::Ready->value);
    }
}
