<?php

/**
 * Controlled promotional discount with validity and usage ceilings.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Controlled promotional discount with validity and usage ceilings. */
final class Promotion extends TravelToursModel
{
    protected $table = 'travel_promotions';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_type' => AdjustmentType::class, 'adjustment_value' => 'integer',
            'valid_from' => 'datetime', 'valid_until' => 'datetime', 'minimum_booking_minor' => 'integer',
            'minimum_participants' => 'integer', 'maximum_uses' => 'integer',
            'maximum_uses_per_customer' => 'integer', 'applies_to_all_tours' => 'boolean',
            'is_active' => 'boolean', 'conditions' => 'array',
        ];
    }

    /**
     * Query related Tour records through travel_promotion_tour.
     *
     * @return BelongsToMany<Tour, $this>
     */
    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'travel_promotion_tour')->withPivot('is_exclusion')->withTimestamps();
    }

    /**
     * Query related PromotionRedemption records.
     *
     * @return HasMany<PromotionRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * Apply the redeemable selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $window): Builder => $window->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $window): Builder => $window->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
    }
}
