<?php

/**
 * Date-bound base rate for an adult, child, or infant participant.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Models;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Date-bound base rate for an adult, child, or infant participant. */
final class ParticipantRate extends TravelToursModel
{
    protected $table = 'travel_participant_rates';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'participant_type' => ParticipantType::class, 'minimum_age' => 'integer',
            'maximum_age' => 'integer', 'amount_minor' => 'integer', 'tax_inclusive' => 'boolean',
            'active_from' => 'date', 'active_until' => 'date', 'is_active' => 'boolean',
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
}
