<?php

/**
 * Immutable add-on purchase snapshot attached to a booking.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Modules\TravelTours\Catalog\Models\TourExtra;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable add-on purchase snapshot attached to a booking. */
final class BookingExtra extends TravelToursModel
{
    protected $table = 'travel_booking_extras';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_amount_minor' => 'integer', 'discount_minor' => 'integer',
            'tax_minor' => 'integer', 'total_minor' => 'integer',
        ];
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
     * Resolve the associated TourExtra record.
     *
     * @return BelongsTo<TourExtra, $this>
     */
    public function tourExtra(): BelongsTo
    {
        return $this->belongsTo(TourExtra::class);
    }

    /**
     * Resolve the associated BookingParticipant record through booking_participant_id.
     *
     * @return BelongsTo<BookingParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(BookingParticipant::class, 'booking_participant_id');
    }
}
