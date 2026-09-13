<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Factories;

use App\Models\User;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReceptionShift> */
final class ReceptionShiftFactory extends Factory
{
    protected $model = ReceptionShift::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'register_id' => ReceptionRegister::factory(),
            'property_id' => fn (array $attributes): int => ReceptionRegister::query()->findOrFail($attributes['register_id'])->property_id,
            'receptionist_id' => User::factory(),
            'register_open_guard' => fn (array $attributes): int => (int) $attributes['register_id'],
            'receptionist_open_guard' => fn (array $attributes): int => (int) $attributes['receptionist_id'],
            'status' => ReceptionShiftStatus::Open,
            'currency' => fn (array $attributes): string => ReceptionRegister::query()->findOrFail($attributes['register_id'])->property->currency,
            'opening_float_minor' => 10_000,
            'expected_cash_minor' => 10_000,
            'counted_cash_minor' => null,
            'variance_minor' => null,
            'opening_note' => null,
            'closing_note' => null,
            'opened_by' => fn (array $attributes): int => (int) $attributes['receptionist_id'],
            'opened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    /** Configure the factory for the closed state. */
    public function closed(): static
    {
        return $this->state(fn (): array => [
            'register_open_guard' => null,
            'receptionist_open_guard' => null,
            'status' => ReceptionShiftStatus::Closed,
            'counted_cash_minor' => 10_000,
            'variance_minor' => 0,
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
