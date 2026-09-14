<?php

/**
 * Reusable traveler identity with encrypted travel-sensitive details.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Models;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Models\BookingParticipant;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Reusable traveler identity with encrypted travel-sensitive details. */
final class Traveler extends TravelToursModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'travel_travelers';

    /** Sensitive profile values require an explicit authorized projection. */
    protected $hidden = [
        'identity_number', 'identity_number_hash', 'medical_notes',
        'dietary_requirements', 'accessibility_requirements',
        'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'participant_type' => ParticipantType::class,
            'identity_number' => 'encrypted', 'passport_expiry_date' => 'date',
            'dietary_requirements' => 'encrypted', 'accessibility_requirements' => 'encrypted',
            'medical_notes' => 'encrypted', 'is_active' => 'boolean',
        ];
    }

    /** Declare allowed collections, MIME types and storage visibility for this resource. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('traveler_profile')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])->useDisk('public');
        $this->addMediaCollection('travel_documents')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->useDisk('private');
    }

    /**
     * Resolve the associated TravelCustomer record.
     *
     * @return BelongsTo<TravelCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(TravelCustomer::class);
    }

    /**
     * Query related BookingParticipant records.
     *
     * @return HasMany<BookingParticipant, $this>
     */
    public function bookingParticipants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class);
    }
}
