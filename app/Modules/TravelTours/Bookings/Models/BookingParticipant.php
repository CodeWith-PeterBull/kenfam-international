<?php

/**
 * Immutable traveler, document, requirement, and fare snapshot.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Customers\Models\Traveler;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Immutable traveler, document, requirement, and fare snapshot. */
final class BookingParticipant extends TravelToursModel
{
    protected $table = 'travel_booking_participants';

    /** Private traveler snapshots never belong in routine booking serialization. */
    protected $hidden = [
        'identity_number', 'medical_notes', 'dietary_requirements',
        'accessibility_requirements', 'emergency_contact_name',
        'emergency_contact_phone',
    ];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer', 'is_lead' => 'boolean', 'participant_type' => ParticipantType::class,
            'date_of_birth' => 'date', 'age_at_departure' => 'integer', 'consumes_seat' => 'boolean',
            'guardian_sequence' => 'integer',
            'identity_number' => 'encrypted', 'passport_expiry_date' => 'date', 'dietary_requirements' => 'encrypted',
            'accessibility_requirements' => 'encrypted', 'medical_notes' => 'encrypted', 'allocated_price_minor' => 'integer',
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
     * Resolve the associated Traveler record.
     *
     * @return BelongsTo<Traveler, $this>
     */
    public function traveler(): BelongsTo
    {
        return $this->belongsTo(Traveler::class);
    }

    /**
     * Query related BookingExtra records.
     *
     * @return HasMany<BookingExtra, $this>
     */
    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class, 'booking_participant_id');
    }
}
