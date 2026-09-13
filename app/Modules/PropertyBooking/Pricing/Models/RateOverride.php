<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Models;

use App\Models\User;
use App\Modules\PropertyBooking\Database\Factories\RateOverrideFactory;
use App\Modules\PropertyBooking\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Non-overlapping local-date price and restriction override. */
final class RateOverride extends Model
{
    /** @use HasFactory<RateOverrideFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'property_booking_rate_overrides';

    /** @var list<string> */
    protected $fillable = [
        'rate_plan_id', 'starts_on', 'ends_on', 'rate_minor', 'is_closed',
        'closed_on_arrival', 'closed_on_departure', 'minimum_units', 'maximum_units', 'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'rate_minor' => 'integer',
            'is_closed' => 'boolean', 'closed_on_arrival' => 'boolean',
            'closed_on_departure' => 'boolean', 'minimum_units' => 'integer',
            'maximum_units' => 'integer',
        ];
    }

    /** Resolve the module-owned model factory. */
    protected static function newFactory(): RateOverrideFactory
    {
        return RateOverrideFactory::new();
    }

    /** Get the rate plan relationship. */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'rate_plan_id');
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

    /** Constrain the query to overlapping dates records. */
    public function scopeOverlappingDates(Builder $query, string $startsOn, string $endsOn): Builder
    {
        return $query->where('starts_on', '<', $endsOn)->where('ends_on', '>', $startsOn);
    }
}
