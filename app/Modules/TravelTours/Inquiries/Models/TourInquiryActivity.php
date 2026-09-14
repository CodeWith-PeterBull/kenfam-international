<?php

/**
 * Append-only communication, assignment, state, and conversion timeline item.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Models;

use App\Models\User;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only communication, assignment, state, and conversion timeline item. */
final class TourInquiryActivity extends TravelToursModel
{
    protected $table = 'travel_tour_inquiry_activities';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /**
     * Resolve the associated TourInquiry record.
     *
     * @return BelongsTo<TourInquiry, $this>
     */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(TourInquiry::class);
    }

    /**
     * Resolve the associated User record through actor_id.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
