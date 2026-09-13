<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Database\Factories\PropertyFactory;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
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

/** Accommodation establishment, address, timezone, and policy defaults. */
final class Property extends Model implements HasMedia
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use HasUlid;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'property_booking_properties';

    /** @var list<string> */
    protected $fillable = [
        'category_id', 'code', 'slug', 'name', 'status', 'short_description', 'description',
        'house_rules', 'cancellation_summary', 'email', 'phone', 'whatsapp_phone', 'website_url',
        'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code',
        'latitude', 'longitude', 'timezone', 'currency', 'check_in_from', 'check_in_until',
        'check_out_from', 'check_out_until', 'minimum_notice_minutes', 'maximum_advance_days',
        'turnover_minutes', 'is_featured', 'meta_title', 'meta_description', 'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'minimum_notice_minutes' => 'integer',
            'maximum_advance_days' => 'integer',
            'turnover_minutes' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): PropertyFactory
    {
        return PropertyFactory::new();
    }

    /** Register public cover and ordered gallery collections. */
    public function registerMediaCollections(): void
    {
        $accepted = ['image/jpeg', 'image/png', 'image/webp'];
        $this->addMediaCollection('property_cover')->singleFile()->acceptsMimeTypes($accepted)->useDisk('public');
        $this->addMediaCollection('property_gallery')->acceptsMimeTypes($accepted)->useDisk('public');
    }

    /** Register reusable non-upscaled property image sizes. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = ['property_cover', 'property_gallery'];
        $this->addMediaConversion('thumb')->fit(Fit::Max, 320, 240)->performOnCollections(...$collections);
        $this->addMediaConversion('card')->fit(Fit::Max, 720, 540)->performOnCollections(...$collections);
        $this->addMediaConversion('detail')->fit(Fit::Max, 1600, 1200)->performOnCollections(...$collections);
    }

    /** Get the category relationship. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
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

    /** Get the assigned users relationship. */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_booking_property_user')
            ->withPivot(['assigned_by', 'is_default'])
            ->withTimestamps();
    }

    /** Get the amenities relationship. */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_booking_property_amenity')
            ->withPivot('detail')
            ->withTimestamps();
    }

    /** Get the unit types relationship. */
    public function unitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class, 'property_id');
    }

    /** Get the units relationship. */
    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class, 'property_id');
    }

    /** Get the rate plans relationship. */
    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class, 'property_id');
    }

    /** Get the availability blocks relationship. */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class, 'property_id');
    }

    /** Get the registers relationship. */
    public function registers(): HasMany
    {
        return $this->hasMany(ReceptionRegister::class, 'property_id');
    }

    /** Get the shifts relationship. */
    public function shifts(): HasMany
    {
        return $this->hasMany(ReceptionShift::class, 'property_id');
    }

    /** Get the bookings relationship. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'property_id');
    }

    /** Get the unit assignments relationship. */
    public function unitAssignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class, 'property_id');
    }

    /** Constrain the query to published records. */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PropertyStatus::Published->value)
            ->where(static fn (Builder $published): Builder => $published
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }
}
