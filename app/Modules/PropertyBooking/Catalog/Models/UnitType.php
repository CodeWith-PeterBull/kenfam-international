<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Catalog\Enums\UnitTypeStatus;
use App\Modules\PropertyBooking\Database\Factories\UnitTypeFactory;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Sellable accommodation description backed by concrete units. */
final class UnitType extends Model implements HasMedia
{
    /** @use HasFactory<UnitTypeFactory> */
    use HasFactory;

    use HasUlid;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'property_booking_unit_types';

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'code', 'slug', 'name', 'status', 'short_description', 'description',
        'size_square_metres', 'bedroom_count', 'bathroom_count', 'living_room_count',
        'bed_count', 'bed_configuration', 'maximum_guests', 'maximum_adults',
        'maximum_children', 'maximum_infants', 'allows_infants_on_top', 'is_entire_unit',
        'smoking_allowed', 'is_featured', 'meta_title', 'meta_description', 'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UnitTypeStatus::class,
            'size_square_metres' => 'decimal:2',
            'bedroom_count' => 'integer', 'bathroom_count' => 'integer',
            'living_room_count' => 'integer', 'bed_count' => 'integer',
            'bed_configuration' => 'array', 'maximum_guests' => 'integer',
            'maximum_adults' => 'integer', 'maximum_children' => 'integer',
            'maximum_infants' => 'integer', 'allows_infants_on_top' => 'boolean',
            'is_entire_unit' => 'boolean', 'smoking_allowed' => 'boolean',
            'is_featured' => 'boolean', 'published_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): UnitTypeFactory
    {
        return UnitTypeFactory::new();
    }

    /** Register the model-owned media collections. */
    public function registerMediaCollections(): void
    {
        $accepted = ['image/jpeg', 'image/png', 'image/webp'];
        $this->addMediaCollection('unit_type_cover')->singleFile()->acceptsMimeTypes($accepted)->useDisk('public');
        $this->addMediaCollection('unit_type_gallery')->acceptsMimeTypes($accepted)->useDisk('public');
    }

    /** Register reusable non-upscaled unit-type image sizes. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = ['unit_type_cover', 'unit_type_gallery'];
        $this->addMediaConversion('thumb')->fit(Fit::Max, 320, 240)->performOnCollections(...$collections);
        $this->addMediaConversion('card')->fit(Fit::Max, 720, 540)->performOnCollections(...$collections);
        $this->addMediaConversion('detail')->fit(Fit::Max, 1600, 1200)->performOnCollections(...$collections);
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

    /** Get the amenities relationship. */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_booking_unit_type_amenity')
            ->withPivot('detail')
            ->withTimestamps();
    }

    /** Get the units relationship. */
    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class, 'unit_type_id');
    }

    /** Get the rate plans relationship. */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class, 'unit_type_id');
    }

    /** Get the booking stays relationship. */
    public function bookingStays(): HasMany
    {
        return $this->hasMany(BookingStay::class, 'unit_type_id');
    }

    /** Constrain the query to published records. */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', UnitTypeStatus::Published->value)
            ->where(static fn (Builder $published): Builder => $published
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /** Constrain the query to fits occupancy records. */
    public function scopeFitsOccupancy(Builder $query, int $adults, int $children, int $infants = 0): Builder
    {
        return $query
            ->where('maximum_adults', '>=', $adults)
            ->where('maximum_children', '>=', $children)
            ->where('maximum_infants', '>=', $infants)
            ->where('maximum_guests', '>=', $adults + $children);
    }
}
