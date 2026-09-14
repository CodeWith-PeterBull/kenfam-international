<?php

/**
 * Deterministic seasonal, group, or early-booking price adjustment.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Enums\PricingRuleType;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Deterministic seasonal, group, or early-booking price adjustment. */
final class PricingRule extends TravelToursModel
{
    protected $table = 'travel_pricing_rules';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rule_type' => PricingRuleType::class, 'travel_starts_on' => 'date', 'travel_ends_on' => 'date',
            'sales_start_at' => 'datetime', 'sales_end_at' => 'datetime', 'minimum_participants' => 'integer',
            'minimum_advance_days' => 'integer', 'adjustment_type' => AdjustmentType::class,
            'adjustment_value' => 'integer', 'priority' => 'integer', 'is_stackable' => 'boolean',
            'is_active' => 'boolean', 'conditions' => 'array',
        ];
    }

    /**
     * Resolve the associated TourRatePlan record through rate_plan_id.
     *
     * @return BelongsTo<TourRatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(TourRatePlan::class, 'rate_plan_id');
    }

    /**
     * Resolve the associated TourDeparture record.
     *
     * @return BelongsTo<TourDeparture, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class);
    }

    /**
     * Apply the active selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('priority');
    }
}
