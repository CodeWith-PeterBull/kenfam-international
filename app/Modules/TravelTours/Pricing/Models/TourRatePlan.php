<?php

/**
 * Currency, tax, deposit, and participant-pricing policy for a tour.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Currency, tax, deposit, and participant-pricing policy for a tour. */
final class TourRatePlan extends TravelToursModel
{
    protected $table = 'travel_tour_rate_plans';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_inclusive' => 'boolean', 'tax_rate_basis_points' => 'integer',
            'deposit_value' => 'integer', 'balance_due_days' => 'integer',
            'is_refundable' => 'boolean', 'booking_restrictions' => 'array',
            'is_active' => 'boolean', 'is_public' => 'boolean',
            'is_default' => 'boolean', 'minimum_participants' => 'integer',
            'maximum_participants' => 'integer', 'display_order' => 'integer',
        ];
    }

    /**
     * Resolve the associated Tour record.
     *
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Query related ParticipantRate records through rate_plan_id.
     *
     * @return HasMany<ParticipantRate, $this>
     */
    public function participantRates(): HasMany
    {
        return $this->hasMany(ParticipantRate::class, 'rate_plan_id');
    }

    /**
     * Query related PricingRule records through rate_plan_id.
     *
     * @return HasMany<PricingRule, $this>
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class, 'rate_plan_id')->orderBy('priority');
    }

    /**
     * Query related TourDeparture records through rate_plan_id.
     *
     * @return HasMany<TourDeparture, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class, 'rate_plan_id');
    }

    /**
     * Query related TourBooking records through rate_plan_id.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class, 'rate_plan_id');
    }

    /**
     * Apply the publicly available selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true);
    }
}
