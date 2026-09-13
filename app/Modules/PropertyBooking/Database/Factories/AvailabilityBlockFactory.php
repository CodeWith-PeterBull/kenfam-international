<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityBlock> */
final class AvailabilityBlockFactory extends Factory
{
    protected $model = AvailabilityBlock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_id' => AccommodationUnit::factory(),
            'property_id' => fn (array $attributes): int => AccommodationUnit::query()->findOrFail($attributes['unit_id'])->property_id,
            'type' => AvailabilityBlockType::Maintenance,
            'status' => AvailabilityBlockStatus::Active,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'reason' => 'Scheduled preventive maintenance',
            'internal_note' => null,
            'created_by' => User::factory(),
            'released_by' => null,
            'released_at' => null,
        ];
    }

    /** Retain a released historical block that no longer consumes availability. */
    public function released(): static
    {
        return $this->state(fn (): array => [
            'status' => AvailabilityBlockStatus::Released,
            'released_by' => User::factory(),
            'released_at' => now(),
        ]);
    }
}
