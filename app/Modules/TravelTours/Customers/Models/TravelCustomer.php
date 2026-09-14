<?php

/**
 * Normalized booking customer and communication-consent profile.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Customers\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Customers\Enums\CustomerStatus;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Pricing\Models\PromotionRedemption;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Normalized booking customer and communication-consent profile. */
final class TravelCustomer extends TravelToursModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'travel_customers';

    /** Lookup hashes and operator notes are not public profile attributes. */
    protected $hidden = ['email_hash', 'phone_hash', 'notes'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'contact_hash_version' => 'integer',
            'email_verified_at' => 'datetime', 'phone_verified_at' => 'datetime',
            'email_consent' => 'boolean', 'sms_consent' => 'boolean', 'whatsapp_consent' => 'boolean',
            'consent_recorded_at' => 'datetime', 'status' => CustomerStatus::class,
        ];
    }

    /** Declare allowed collections, MIME types and storage visibility for this resource. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('customer_profile')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])->useDisk('public');
    }

    /**
     * Resolve the associated User record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve the associated User record through created_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the associated User record through updated_by.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Query related Traveler records through customer_id.
     *
     * @return HasMany<Traveler, $this>
     */
    public function travelers(): HasMany
    {
        return $this->hasMany(Traveler::class, 'customer_id');
    }

    /**
     * Query related AvailabilityHold records through customer_id.
     *
     * @return HasMany<AvailabilityHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(AvailabilityHold::class, 'customer_id');
    }

    /**
     * Query related TourBooking records through customer_id.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class, 'customer_id');
    }

    /**
     * Query related TourInquiry records through customer_id.
     *
     * @return HasMany<TourInquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(TourInquiry::class, 'customer_id');
    }

    /**
     * Query related PromotionRedemption records through customer_id.
     *
     * @return HasMany<PromotionRedemption, $this>
     */
    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class, 'customer_id');
    }

    /** Format only supplied name parts for operator-facing identification. */
    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }
}
