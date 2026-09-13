<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RatePlan> */
final class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_type_id' => UnitType::factory(),
            'property_id' => fn (array $attributes): int => UnitType::query()->findOrFail($attributes['unit_type_id'])->property_id,
            'created_by' => null,
            'updated_by' => null,
            'code' => 'RATE-'.strtoupper(fake()->unique()->bothify('??###')),
            'name' => 'Flexible nightly rate',
            'description' => 'A flexible accommodation rate for demonstration and automated tests.',
            'status' => RatePlanStatus::Draft,
            'pricing_unit' => StayPricingUnit::Night,
            'currency' => config('property-booking.defaults.currency', 'KES'),
            'base_rate_minor' => fake()->numberBetween(500_000, 2_500_000),
            'included_adults' => 1,
            'included_children' => 0,
            'extra_adult_minor' => 100_000,
            'extra_child_minor' => 50_000,
            'minimum_units' => 1,
            'maximum_units' => 30,
            'minimum_advance_minutes' => 0,
            'maximum_advance_days' => 365,
            'tax_rate_bps' => config('property-booking.defaults.tax_rate_bps', 0),
            'is_tax_inclusive' => config('property-booking.defaults.prices_include_tax', true),
            'deposit_type' => DepositType::Percentage,
            'deposit_amount_minor' => null,
            'deposit_rate_bps' => 5000,
            'is_refundable' => true,
            'free_cancel_before_minutes' => 1440,
            'cancellation_terms' => 'Free cancellation is available until the stated cutoff.',
            'is_public' => true,
            'sort_order' => 0,
            'published_at' => null,
        ];
    }

    /** Configure the factory for the active state. */
    public function active(): static
    {
        return $this->state(fn (): array => ['status' => RatePlanStatus::Active, 'published_at' => now()]);
    }

    /** Configure whole-hour short-stay billing. */
    public function hourly(): static
    {
        return $this->state(fn (): array => [
            'pricing_unit' => StayPricingUnit::Hour,
            'minimum_units' => 2,
            'maximum_units' => 12,
        ]);
    }

    /** Configure a single or multi-day day-use plan. */
    public function dayUse(): static
    {
        return $this->state(fn (): array => [
            'pricing_unit' => StayPricingUnit::DayUse,
            'minimum_units' => 1,
            'maximum_units' => 7,
        ]);
    }

    /** Configure nightly billing explicitly. */
    public function nightly(): static
    {
        return $this->state(fn (): array => ['pricing_unit' => StayPricingUnit::Night]);
    }

    /** Attach the factory graph to the requested unit type. */
    public function forUnitType(UnitType $unitType): static
    {
        return $this->state(fn (): array => [
            'unit_type_id' => $unitType->id,
            'property_id' => $unitType->property_id,
            'currency' => $unitType->property->currency,
        ]);
    }
}
