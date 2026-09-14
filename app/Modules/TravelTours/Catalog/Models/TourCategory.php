<?php

/**
 * Hierarchical merchandising and discovery classification for tours.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Models;

use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hierarchical merchandising and discovery classification for tours. */
final class TourCategory extends TravelToursModel
{
    protected $table = 'travel_tour_categories';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
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
     * Query related Tour records through travel_tour_category.
     *
     * @return BelongsToMany<Tour, $this>
     */
    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'travel_tour_category', 'category_id', 'tour_id')
            ->using(TourCategoryAssignment::class)
            ->withPivot(['ulid', 'is_primary', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Apply the active selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
