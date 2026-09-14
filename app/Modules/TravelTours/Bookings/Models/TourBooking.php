<?php

/**
 * Immutable-price reservation aggregate and lifecycle owner.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\PromotionRedemption;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Immutable-price reservation aggregate and lifecycle owner. */
final class TourBooking extends TravelToursModel
{
    protected $table = 'travel_tour_bookings';

    /** Hide operational idempotency and private request data from routine serialization. */
    protected $hidden = ['operation_key', 'special_requests', 'pricing_snapshot', 'attribution'];

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => BookingChannel::class, 'confirmation_mode' => BookingMode::class,
            'status' => BookingStatus::class, 'payment_status' => PaymentStatus::class,
            'adult_count' => 'integer', 'child_count' => 'integer', 'infant_count' => 'integer', 'seat_count' => 'integer',
            'subtotal_minor' => 'integer', 'extras_total_minor' => 'integer', 'discount_total_minor' => 'integer',
            'tax_total_minor' => 'integer', 'total_minor' => 'integer', 'deposit_required_minor' => 'integer',
            'paid_minor' => 'integer', 'refunded_minor' => 'integer', 'currency_exponent' => 'integer',
            'preferred_payment_method' => PaymentMethod::class,
            'departure_starts_at_snapshot' => 'datetime', 'departure_ends_at_snapshot' => 'datetime',
            'meeting_point_snapshot' => 'array', 'rate_plan_snapshot' => 'array', 'pricing_snapshot' => 'array',
            'special_requests' => 'encrypted', 'attribution' => 'array',
            'terms_accepted_at' => 'datetime',
            'placed_at' => 'datetime', 'pending_expires_at' => 'datetime', 'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'expired_at' => 'datetime',
            'public_access_version' => 'integer',
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
     * Resolve the associated AvailabilityHold record.
     *
     * @return BelongsTo<AvailabilityHold, $this>
     */
    public function availabilityHold(): BelongsTo
    {
        return $this->belongsTo(AvailabilityHold::class);
    }

    /**
     * Resolve the associated TourRatePlan record.
     *
     * @return BelongsTo<TourRatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(TourRatePlan::class);
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

    /**
     * Resolve the associated BookingRegister record.
     *
     * @return BelongsTo<BookingRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(BookingRegister::class);
    }

    /**
     * Resolve the associated BookingShift record.
     *
     * @return BelongsTo<BookingShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(BookingShift::class);
    }

    /**
     * Resolve the associated User record through agent_id.
     *
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
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
     * Query related BookingParticipant records through booking_id.
     *
     * @return HasMany<BookingParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class, 'booking_id');
    }

    /**
     * Query related BookingExtra records through booking_id.
     *
     * @return HasMany<BookingExtra, $this>
     */
    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class, 'booking_id');
    }

    /**
     * Query related BookingPriceLine records through booking_id.
     *
     * @return HasMany<BookingPriceLine, $this>
     */
    public function priceLines(): HasMany
    {
        return $this->hasMany(BookingPriceLine::class, 'booking_id')->orderBy('display_order');
    }

    /**
     * Query related BookingStatusHistory records through booking_id.
     *
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class, 'booking_id')->orderBy('changed_at');
    }

    /**
     * Query related PaymentSchedule records through booking_id.
     *
     * @return HasMany<PaymentSchedule, $this>
     */
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'booking_id')->orderBy('instalment_number');
    }

    /**
     * Query related BookingPayment records through booking_id.
     *
     * @return HasMany<BookingPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'booking_id');
    }

    /**
     * Query related BookingRefund records through booking_id.
     *
     * @return HasMany<BookingRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'booking_id');
    }

    /**
     * Query related PromotionRedemption records through booking_id.
     *
     * @return HasOne<PromotionRedemption, $this>
     */
    public function promotionRedemption(): HasOne
    {
        return $this->hasOne(PromotionRedemption::class, 'booking_id');
    }

    /**
     * Apply the capacity consuming selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCapacityConsuming(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (BookingStatus $status): string => $status->value,
            array_filter(BookingStatus::cases(), static fn (BookingStatus $status): bool => $status->reservesCapacity()),
        ));
    }

    /** Read the unpaid minor-unit balance; payment writes belong to the payment service. */
    protected function balanceMinor(): Attribute
    {
        return Attribute::get(function (): int {
            $netPaid = max((int) $this->paid_minor - (int) $this->refunded_minor, 0);

            return max((int) $this->total_minor - $netPaid, 0);
        });
    }
}
