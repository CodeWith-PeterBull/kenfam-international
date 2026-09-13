<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Factories;

use App\Models\User;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TillSession>
 */
final class TillSessionFactory extends Factory
{
    protected $model = TillSession::class;

    /**
     * Define an open cashier till session.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingFloat = 10_000;

        return [
            'register_id' => Register::factory(),
            'opened_by' => User::factory(),
            'closed_by' => null,
            'status' => TillSessionStatus::Open,
            'opening_float_minor' => $openingFloat,
            'expected_cash_minor' => $openingFloat,
            'counted_cash_minor' => null,
            'variance_minor' => null,
            'opening_note' => null,
            'closing_note' => null,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }

    /**
     * Mark the generated till session as reconciled and closed.
     */
    public function closed(int $varianceMinor = 0): static
    {
        return $this->state(function (array $attributes) use ($varianceMinor): array {
            $expected = (int) ($attributes['expected_cash_minor'] ?? 10_000);

            return [
                'closed_by' => User::factory(),
                'status' => TillSessionStatus::Closed,
                'counted_cash_minor' => $expected + $varianceMinor,
                'variance_minor' => $varianceMinor,
                'closed_at' => now(),
            ];
        });
    }
}
