<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Models;

use App\Modules\PropertyBooking\Database\Factories\PropertyCategoryFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Hierarchical classification for accommodation establishments. */
final class PropertyCategory extends Model implements HasMedia
{
    /** @use HasFactory<PropertyCategoryFactory> */
    use HasFactory;

    use HasUlid;
    use InteractsWithMedia;

    protected $table = 'property_booking_categories';

    /** @var list<string> */
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'sort_order', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): PropertyCategoryFactory
    {
        return PropertyCategoryFactory::new();
    }

    /** Register the replaceable single category image. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('category_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    /** Register a bounded non-upscaled category navigation image. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 480, 360)
            ->performOnCollections('category_image');
    }

    /** Get the parent relationship. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Get the children relationship. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** Get the properties relationship. */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'category_id');
    }

    /** Constrain the query to active records. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Constrain the query to root records. */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
