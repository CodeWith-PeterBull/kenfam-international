<?php

/**
 * Auditable immutable component of a booking calculation.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Auditable immutable component of a booking calculation. */
final class BookingPriceLine extends TravelToursModel
{
    protected $table = 'travel_booking_price_lines';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_amount_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'calculation_metadata' => 'array', 'display_order' => 'integer'];
    }

    /**
     * Resolve the associated TourBooking record.
     *
     * @return BelongsTo<TourBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class);
    }

    /**
     * Resolve the associated PricingRule record.
     *
     * @return BelongsTo<PricingRule, $this>
     */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    /**
     * Resolve the associated Promotion record.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
