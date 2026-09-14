<?php

/**
 * General, tour-specific, private, or custom travel sales inquiry.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** General, tour-specific, private, or custom travel sales inquiry. */
final class TourInquiry extends TravelToursModel
{
    protected $table = 'travel_tour_inquiries';

    /** Keep contact, consent, and idempotency data out of routine serialization. */
    protected $hidden = [
        'operation_key', 'contact_email', 'contact_phone', 'message',
        'consent_ip', 'requested_destinations',
    ];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inquiry_type' => InquiryType::class, 'requested_destinations' => 'array',
            'whatsapp_preferred' => 'boolean',
            'preferred_start_date' => 'date', 'preferred_end_date' => 'date',
            'adult_count' => 'integer', 'child_count' => 'integer', 'infant_count' => 'integer', 'budget_minor' => 'integer',
            'status' => InquiryStatus::class, 'follow_up_at' => 'datetime', 'responded_at' => 'datetime',
            'closed_at' => 'datetime', 'consent_recorded_at' => 'datetime',
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
     * Resolve the associated TourDeparture record.
     *
     * @return BelongsTo<TourDeparture, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class);
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
     * Resolve the associated TourBooking record through converted_booking_id.
     *
     * @return BelongsTo<TourBooking, $this>
     */
    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(TourBooking::class, 'converted_booking_id');
    }

    /**
     * Resolve the associated User record through assigned_to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Query related TourInquiryActivity records through inquiry_id.
     *
     * @return HasMany<TourInquiryActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TourInquiryActivity::class, 'inquiry_id')->orderBy('occurred_at');
    }

    /**
     * Apply the actionable selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActionable(Builder $query): Builder
    {
        return $query->whereNotIn('status', [InquiryStatus::Converted->value, InquiryStatus::Closed->value, InquiryStatus::Spam->value]);
    }
}
