<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Models;

use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Database\Factories\BookingStayFactory;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Immutable unit-type, rate, occupancy, and total snapshot for one reserved unit. */
final class BookingStay extends Model
{
    /** @use HasFactory<BookingStayFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_stays';

    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'pricing_unit' => StayPricingUnit::class,
            'billable_units' => 'integer', 'adult_count' => 'integer', 'child_count' => 'integer',
            'infant_count' => 'integer', 'unit_rate_minor' => 'integer', 'extra_guest_minor' => 'integer',
            'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_rate_bps' => 'integer',
            'tax_minor' => 'integer', 'total_minor' => 'integer', 'is_tax_inclusive' => 'boolean',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): BookingStayFactory
    {
        return BookingStayFactory::new();
    }

    /** Get the booking relationship. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** Get the unit type relationship. */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    /** Get the rate plan relationship. */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'rate_plan_id');
    }

    /** Get the active assignment relationship. */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(UnitAssignment::class, 'booking_stay_id')->where('status', 'active');
    }

    /** Get the assignments relationship. */
    public function assignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class, 'booking_stay_id');
    }

    /** Get the charges relationship. */
    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class, 'booking_stay_id');
    }
}
