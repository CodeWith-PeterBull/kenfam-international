<?php

/**
 * Capacity-governed dated instance of a tour.
 *
 * Schema ownership and retention: .docs/TravelTours/travel-tours-data-model.md.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Models;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Support\TravelToursModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Capacity-governed dated instance of a tour. */
final class TourDeparture extends TravelToursModel
{
    use SoftDeletes;

    protected $table = 'travel_tour_departures';

    /**
     * Preserve declared domain types when hydrating persisted attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'booking_mode' => BookingMode::class,
            'booking_opens_at' => 'datetime', 'booking_closes_at' => 'datetime', 'capacity' => 'integer',
            'minimum_participants' => 'integer',
            'waitlist_enabled' => 'boolean', 'status' => DepartureStatus::class,
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
     * Resolve the associated TourRatePlan record through rate_plan_id.
     *
     * @return BelongsTo<TourRatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(TourRatePlan::class, 'rate_plan_id');
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
     * Query related AvailabilityHold records through departure_id.
     *
     * @return HasMany<AvailabilityHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(AvailabilityHold::class, 'departure_id');
    }

    /**
     * Query related PricingRule records through departure_id.
     *
     * @return HasMany<PricingRule, $this>
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class, 'departure_id')->orderBy('priority');
    }

    /**
     * Query related TourBooking records through departure_id.
     *
     * @return HasMany<TourBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class, 'departure_id');
    }

    /**
     * Query related User records through travel_departure_staff.
     *
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'travel_departure_staff', 'departure_id', 'user_id')
            ->using(DepartureStaffAssignment::class)
            ->withPivot(['ulid', 'role', 'is_lead', 'notes', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Apply the bookable selection constraints without mutating records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->whereIn('status', [DepartureStatus::Open->value, DepartureStatus::Guaranteed->value])
            ->where('starts_at', '>', now())
            ->where(fn (Builder $window): Builder => $window->whereNull('booking_opens_at')->orWhere('booking_opens_at', '<=', now()))
            ->where(fn (Builder $window): Builder => $window->whereNull('booking_closes_at')->orWhere('booking_closes_at', '>=', now()));
    }

    /** Calculate available seats using current persisted bookings and active holds. */
    public function availableSeats(): int
    {
        $booked = $this->bookings()->whereIn('status', array_map(
            static fn (BookingStatus $status): string => $status->value,
            array_filter(BookingStatus::cases(), static fn (BookingStatus $status): bool => $status->reservesCapacity()),
        ))->sum('seat_count');
        $held = $this->holds()->where('status', HoldStatus::Active->value)->where('expires_at', '>', now())->sum('seat_count');

        return max((int) $this->capacity - (int) $booked - (int) $held, 0);
    }
}
