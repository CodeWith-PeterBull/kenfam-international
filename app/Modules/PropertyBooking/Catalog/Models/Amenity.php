<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Models;

use App\Modules\PropertyBooking\Catalog\Enums\AmenityScope;
use App\Modules\PropertyBooking\Database\Factories\AmenityFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Reusable, filterable property or unit-type facility. */
final class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_amenities';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'scope', 'description', 'icon_key', 'sort_order', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scope' => AmenityScope::class, 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): AmenityFactory
    {
        return AmenityFactory::new();
    }

    /** Get the properties relationship. */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_booking_property_amenity')
            ->withPivot('detail')
            ->withTimestamps();
    }

    /** Get the unit types relationship. */
    public function unitTypes(): BelongsToMany
    {
        return $this->belongsToMany(UnitType::class, 'property_booking_unit_type_amenity')
            ->withPivot('detail')
            ->withTimestamps();
    }

    /** Constrain the query to active records. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
