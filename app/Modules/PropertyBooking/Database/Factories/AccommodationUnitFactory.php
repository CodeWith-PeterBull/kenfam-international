<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccommodationUnit> */
final class AccommodationUnitFactory extends Factory
{
    protected $model = AccommodationUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_type_id' => UnitType::factory(),
            'property_id' => fn (array $attributes): int => UnitType::query()->findOrFail($attributes['unit_type_id'])->property_id,
            'created_by' => null,
            'updated_by' => null,
            'code' => 'UNIT-'.strtoupper(fake()->unique()->bothify('??####')),
            'display_name' => 'Room '.fake()->unique()->numberBetween(100, 9999),
            'floor_label' => fake()->randomElement(['Ground floor', 'First floor', 'Second floor']),
            'location_note' => null,
            'operational_status' => UnitOperationalStatus::Ready,
            'is_active' => true,
            'internal_note' => null,
            'last_ready_at' => now(),
        ];
    }

    /** Attach the factory graph to the requested unit type. */
    public function forUnitType(UnitType $unitType): static
    {
        return $this->state(fn (): array => ['unit_type_id' => $unitType->id, 'property_id' => $unitType->property_id]);
    }

    /** Mark the unit unavailable for operational reasons. */
    public function blocked(): static
    {
        return $this->state(fn (): array => [
            'operational_status' => UnitOperationalStatus::Maintenance,
            'last_ready_at' => null,
        ]);
    }
}
