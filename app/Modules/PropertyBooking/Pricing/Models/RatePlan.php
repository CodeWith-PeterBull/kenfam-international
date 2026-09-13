<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Database\Factories\RatePlanFactory;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Bookable pricing, occupancy, duration, deposit, and cancellation policy. */
final class RatePlan extends Model
{
    /** @use HasFactory<RatePlanFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $table = 'property_booking_rate_plans';

    /** @var list<string> */
    protected $fillable = [
        'property_id', 'unit_type_id', 'code', 'name', 'description', 'status',
        'pricing_unit', 'currency', 'base_rate_minor', 'included_adults',
        'included_children', 'extra_adult_minor', 'extra_child_minor', 'minimum_units',
        'maximum_units', 'minimum_advance_minutes', 'maximum_advance_days', 'tax_rate_bps',
        'is_tax_inclusive', 'deposit_type', 'deposit_amount_minor', 'deposit_rate_bps',
        'is_refundable', 'free_cancel_before_minutes', 'cancellation_terms', 'is_public',
        'sort_order', 'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RatePlanStatus::class,
            'pricing_unit' => StayPricingUnit::class,
            'base_rate_minor' => 'integer', 'included_adults' => 'integer',
            'included_children' => 'integer', 'extra_adult_minor' => 'integer',
            'extra_child_minor' => 'integer', 'minimum_units' => 'integer',
            'maximum_units' => 'integer', 'minimum_advance_minutes' => 'integer',
            'maximum_advance_days' => 'integer', 'tax_rate_bps' => 'integer',
            'is_tax_inclusive' => 'boolean', 'deposit_type' => DepositType::class,
            'deposit_amount_minor' => 'integer', 'deposit_rate_bps' => 'integer',
            'is_refundable' => 'boolean', 'free_cancel_before_minutes' => 'integer',
            'is_public' => 'boolean', 'sort_order' => 'integer', 'published_at' => 'datetime',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): RatePlanFactory
    {
        return RatePlanFactory::new();
    }

    /** Get the property relationship. */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** Get the unit type relationship. */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    /** Get the creator relationship. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Get the updater relationship. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Get the overrides relationship. */
    public function overrides(): HasMany
    {
        return $this->hasMany(RateOverride::class, 'rate_plan_id')->orderBy('starts_on');
    }

    /** Get the booking stays relationship. */
    public function bookingStays(): HasMany
    {
        return $this->hasMany(BookingStay::class, 'rate_plan_id');
    }

    /** Constrain the query to publicly bookable records. */
    public function scopePubliclyBookable(Builder $query): Builder
    {
        return $query
            ->where('status', RatePlanStatus::Active->value)
            ->where('is_public', true)
            ->where(static fn (Builder $published): Builder => $published
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }
}
