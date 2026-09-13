<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;

/** Owns rate-plan configuration, lifecycle, and pricing-policy invariants. */
final readonly class RatePlanService
{
    /** Create the rate-plan service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(UnitType $unitType, array $attributes, User $actor): RatePlan
    {
        return $this->database->transaction(function () use ($unitType, $attributes, $actor): RatePlan {
            $unitType = UnitType::query()->with('property')->lockForUpdate()->findOrFail($unitType->getKey());
            $ratePlan = new RatePlan;
            $ratePlan->forceFill([
                'property_id' => $unitType->property_id,
                'unit_type_id' => $unitType->getKey(),
                'status' => RatePlanStatus::Draft,
                'pricing_unit' => StayPricingUnit::Night,
                'currency' => $unitType->property->currency,
                'included_adults' => 1,
                'included_children' => 0,
                'extra_adult_minor' => 0,
                'extra_child_minor' => 0,
                'minimum_units' => 1,
                'minimum_advance_minutes' => 0,
                'tax_rate_bps' => max(0, (int) config('property-booking.defaults.tax_rate_bps', 0)),
                'is_tax_inclusive' => (bool) config('property-booking.defaults.prices_include_tax', true),
                'deposit_type' => DepositType::None,
                'is_refundable' => true,
                'is_public' => true,
                'sort_order' => 0,
            ]);
            $ratePlan->fill($this->payload($attributes));
            $ratePlan->forceFill([
                'property_id' => $unitType->property_id,
                'unit_type_id' => $unitType->getKey(),
                'status' => RatePlanStatus::Draft,
                'published_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->assertValid($ratePlan, $unitType->property, $unitType);
            $ratePlan->save();

            $this->activities->record(
                activityType: 'property-booking.rate-plan.created',
                description: "Rate plan created: {$ratePlan->name}",
                actor: $actor,
                subject: $ratePlan,
                properties: ['property_ulid' => $unitType->property->ulid, 'unit_type_ulid' => $unitType->ulid, 'code' => $ratePlan->code],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pricing',
            );

            return $ratePlan->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(RatePlan $ratePlan, array $attributes, User $actor): RatePlan
    {
        return $this->database->transaction(function () use ($ratePlan, $attributes, $actor): RatePlan {
            $ratePlan = RatePlan::query()->with(['property', 'unitType'])->lockForUpdate()->findOrFail($ratePlan->getKey());
            $ratePlan->fill($this->payload($attributes));
            $ratePlan->forceFill(['updated_by' => $actor->getKey()]);
            $this->assertValid($ratePlan, $ratePlan->property, $ratePlan->unitType);
            $changes = array_values(array_diff(array_keys($ratePlan->getDirty()), ['updated_by']));
            $ratePlan->save();

            $this->activities->record(
                activityType: 'property-booking.rate-plan.updated',
                description: "Rate plan updated: {$ratePlan->name}",
                actor: $actor,
                subject: $ratePlan,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-pricing',
            );

            return $ratePlan->refresh();
        });
    }

    /** Move a rate plan through draft, active, and archived states. */
    public function transition(RatePlan $ratePlan, RatePlanStatus $status, User $actor): RatePlan
    {
        return $this->database->transaction(function () use ($ratePlan, $status, $actor): RatePlan {
            $ratePlan = RatePlan::query()->with(['property', 'unitType'])->lockForUpdate()->findOrFail($ratePlan->getKey());
            $previous = $ratePlan->status;
            if ($previous === $status) {
                return $ratePlan;
            }

            $this->assertValid($ratePlan, $ratePlan->property, $ratePlan->unitType);
            if ($status === RatePlanStatus::Archived && $this->hasAvailabilityConsumingBookings($ratePlan)) {
                throw new RateConfigurationException('A rate plan attached to active bookings cannot be archived.');
            }

            $ratePlan->forceFill([
                'status' => $status,
                'published_at' => $status === RatePlanStatus::Active ? ($ratePlan->published_at ?? now()) : null,
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->activities->record(
                activityType: 'property-booking.rate-plan.status-changed',
                description: "Rate plan status changed to {$status->label()}: {$ratePlan->name}",
                actor: $actor,
                subject: $ratePlan,
                properties: ['from' => $previous->value, 'to' => $status->value],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pricing',
            );

            return $ratePlan->refresh();
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'code', 'name', 'description', 'pricing_unit', 'currency', 'base_rate_minor',
            'included_adults', 'included_children', 'extra_adult_minor', 'extra_child_minor',
            'minimum_units', 'maximum_units', 'minimum_advance_minutes', 'maximum_advance_days',
            'tax_rate_bps', 'is_tax_inclusive', 'deposit_type', 'deposit_amount_minor',
            'deposit_rate_bps', 'is_refundable', 'free_cancel_before_minutes',
            'cancellation_terms', 'is_public', 'sort_order',
        ]);
        foreach (['code', 'currency'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = strtoupper(trim((string) $payload[$field]));
            }
        }
        foreach (['name', 'description', 'cancellation_terms'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = trim((string) $payload[$field]);
                $payload[$field] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /** Validate all monetary, duration, occupancy, tax, and deposit rules. */
    private function assertValid(RatePlan $ratePlan, Property $property, UnitType $unitType): void
    {
        if (blank($ratePlan->code) || blank($ratePlan->name)) {
            throw new RateConfigurationException('Rate plans require a code and name.');
        }
        if ($ratePlan->property_id !== $property->getKey() || $ratePlan->unit_type_id !== $unitType->getKey() || $unitType->property_id !== $property->getKey()) {
            throw new RateConfigurationException('The rate plan, unit type, and property must belong to the same catalog scope.');
        }
        if (strtoupper($ratePlan->currency) !== strtoupper($property->currency)) {
            throw new RateConfigurationException('Rate-plan currency must match its property currency.');
        }
        if (! $ratePlan->pricing_unit instanceof StayPricingUnit || $ratePlan->base_rate_minor < 1) {
            throw new RateConfigurationException('Rate plans require a supported pricing unit and a base rate greater than zero.');
        }
        if ($ratePlan->included_adults < 0 || $ratePlan->included_children < 0
            || $ratePlan->included_adults > $unitType->maximum_adults
            || $ratePlan->included_children > $unitType->maximum_children
            || $ratePlan->included_adults + $ratePlan->included_children > $unitType->maximum_guests) {
            throw new RateConfigurationException('Included occupancy must fit within the selected unit type.');
        }
        if ($ratePlan->minimum_units < 1 || ($ratePlan->maximum_units !== null && $ratePlan->maximum_units < $ratePlan->minimum_units)) {
            throw new RateConfigurationException('Rate-plan duration limits are invalid.');
        }
        if ($ratePlan->minimum_advance_minutes < 0 || ($ratePlan->maximum_advance_days !== null && $ratePlan->maximum_advance_days < 1)) {
            throw new RateConfigurationException('Rate-plan advance-booking limits are invalid.');
        }
        if ($ratePlan->tax_rate_bps < 0 || $ratePlan->tax_rate_bps > 10_000) {
            throw new RateConfigurationException('Rate-plan tax must be between 0 and 100 percent.');
        }
        if ($ratePlan->extra_adult_minor < 0 || $ratePlan->extra_child_minor < 0) {
            throw new RateConfigurationException('Occupancy surcharges cannot be negative.');
        }
        $this->assertDeposit($ratePlan);
    }

    /** Enforce exactly one representation for the configured deposit strategy. */
    private function assertDeposit(RatePlan $ratePlan): void
    {
        $valid = match ($ratePlan->deposit_type) {
            DepositType::None => $ratePlan->deposit_amount_minor === null && $ratePlan->deposit_rate_bps === null,
            DepositType::Fixed => $ratePlan->deposit_amount_minor !== null
                && $ratePlan->deposit_amount_minor > 0
                && $ratePlan->deposit_rate_bps === null,
            DepositType::Percentage => $ratePlan->deposit_amount_minor === null
                && $ratePlan->deposit_rate_bps !== null
                && $ratePlan->deposit_rate_bps > 0
                && $ratePlan->deposit_rate_bps <= 10_000,
        };
        if (! $valid) {
            throw new RateConfigurationException('The rate-plan deposit configuration is inconsistent.');
        }
    }

    /** Determine whether active bookings still depend on this rate. */
    private function hasAvailabilityConsumingBookings(RatePlan $ratePlan): bool
    {
        $statuses = collect(BookingStatus::cases())
            ->filter(static fn (BookingStatus $status): bool => $status->consumesAvailability())
            ->map(static fn (BookingStatus $status): string => $status->value)
            ->all();

        return $ratePlan->bookingStays()->whereHas('booking', static fn ($query) => $query->whereIn('status', $statuses))->exists();
    }
}
