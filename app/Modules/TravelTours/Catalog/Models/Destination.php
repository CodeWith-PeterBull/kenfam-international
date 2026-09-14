<?php

/**
 * Searchable geographical hierarchy and public destination content.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Enums\DestinationType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Searchable geographical hierarchy and public destination content. */
final class Destination extends TravelToursModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'travel_destinations';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DestinationType::class,
            'status' => PublicationStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** Declare allowed collections, MIME types and storage visibility for this resource. */
    public function registerMediaCollections(): void
    {
        $images = ['image/jpeg', 'image/png', 'image/webp'];
        $this->addMediaCollection('destination_cover')->singleFile()->acceptsMimeTypes($images)->useDisk('public');
        $this->addMediaCollection('destination_gallery')->acceptsMimeTypes($images)->useDisk('public');
    }

    /** Define bounded image derivatives for the declared public image collections. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = ['destination_cover', 'destination_gallery'];
        $this->addMediaConversion('thumb')->fit(Fit::Max, 360, 240)->performOnCollections(...$collections);
        $this->addMediaConversion('card')->fit(Fit::Max, 960, 640)->performOnCollections(...$collections);
        $this->addMediaConversion('hero')->fit(Fit::Max, 1920, 1080)->performOnCollections(...$collections);
    }

    /**
     * Resolve the associated self record through parent_id.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Query related self records through parent_id.
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Resolve the associated User record through created_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the associated User record through updated_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Query related Tour records through travel_tour_destination.
     *
     * @return BelongsToMany<Tour, $this>
     */
    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'travel_tour_destination', 'destination_id', 'tour_id')
            ->using(TourDestinationAssignment::class)
            ->withPivot(['ulid', 'role', 'sequence', 'is_overnight'])
            ->withTimestamps();
    }

    /**
     * Apply the published selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published->value)
            ->where('is_active', true)
            ->where(fn (Builder $published): Builder => $published->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
